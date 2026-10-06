<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\SponsorDrawStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\GlobalUser;
use App\Models\NotificationChannel;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\SponsorDrawNotification;
use App\Models\SponsorDrawResult;
use App\Models\SponsorDrawSession;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Realtime\RealtimeEmitter;
use App\Services\Realtime\SocketTokenService;
use App\Services\SponsorDraw\SponsorDrawEventLog;
use App\Services\SponsorDraw\SponsorDrawRandomizer;
use App\Services\SponsorDraw\SponsorDrawService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Sponsor draw landing page (documents/upgrade/yeu-cau-landing-page-quay-sponsor.md): eligibility snapshot,
 * access link, backend-only winner selection, limits, confirmation, per-channel delivery, verifiable
 * randomness, hash-chained events and realtime publication after commit.
 */
class SponsorDrawTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Admin $admin;

    private Campaign $campaign;

    /** @var list<RoomUser> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->room = Room::create(['name' => 'Draw room', 'slug' => 'draw-room', 'status' => 'active']);
        $this->admin = Admin::create(['name' => 'Room Admin', 'email' => 'draw-admin@example.test', 'password' => 'password123', 'status' => 'active']);
        $this->admin->rooms()->attach($this->room);
        $this->campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'Friday tea', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);
        foreach (['An', 'Binh', 'Chi', 'Dung', 'Em'] as $name) {
            $this->members[] = $this->member($name);
        }
    }

    private function member(string $name, string $status = 'active', string $globalStatus = 'active', ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower($name).uniqid().'@example.test', 'status' => $globalStatus]);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => $status]);
    }

    /**
     * @return array{session: SponsorDrawSession, token: string}
     */
    private function create(int $winners = 2, ?Campaign $target = null): array
    {
        return app(SponsorDrawService::class)->create($this->room, $this->admin, $this->campaign, $winners, $target);
    }

    private function landing(string $token): string
    {
        return route('user.sponsor-draws.show', [$this->room, $token]);
    }

    public function test_snapshot_keeps_active_members_who_did_not_decline_and_stays_frozen(): void
    {
        $this->member('Inactive member', 'blocked');
        $this->member('Blocked account', 'active', 'blocked');
        CampaignParticipant::create(['campaign_id' => $this->campaign->id, 'room_user_id' => $this->members[4]->id, 'status' => CampaignParticipant::STATUS_DECLINED]);
        $this->member('Other room member', 'active', 'active', Room::create(['name' => 'Other', 'slug' => 'other-room', 'status' => 'active']));

        $session = $this->create()['session'];

        $this->assertSame(4, $session->eligible_count);
        $this->assertSame(['An', 'Binh', 'Chi', 'Dung'], $session->eligibleUsers()->pluck('display_name_snapshot')->all());
        // Later changes never alter the published snapshot.
        $this->members[0]->update(['status' => 'blocked']);
        $this->member('Late joiner');
        $this->assertSame(4, $session->fresh()->eligibleUsers()->count());
    }

    public function test_creation_is_validated(): void
    {
        $service = app(SponsorDrawService::class);
        $empty = Campaign::create(['room_id' => Room::create(['name' => 'Empty', 'slug' => 'empty-room', 'status' => 'active'])->id, 'name' => 'Empty', 'restaurant' => 'X', 'status' => CampaignStatus::Closed]);

        $this->assertValidationFails(fn () => $service->create($empty->room, $this->admin, $empty, 1), 'campaign_id');
        $this->assertValidationFails(fn () => $this->create(6), 'target_winner_count');
        $this->assertValidationFails(fn () => $this->create(0), 'target_winner_count');
        $closedTarget = Campaign::create(['room_id' => $this->room->id, 'name' => 'Closed', 'restaurant' => 'X', 'status' => CampaignStatus::Closed]);
        $this->assertValidationFails(fn () => $this->create(1, $closedTarget), 'target_campaign_id');
        $this->assertValidationFails(fn () => $this->create(1, $this->campaign), 'target_campaign_id');
        $this->assertSame(0, SponsorDrawSession::query()->count());

        $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.store', $this->room), ['campaign_id' => $this->campaign->id, 'target_winner_count' => 0])
            ->assertSessionHasErrors('target_winner_count');
    }

    public function test_admin_creates_a_session_with_an_optional_target_campaign(): void
    {
        $target = Campaign::create(['room_id' => $this->room->id, 'name' => 'Next week', 'restaurant' => 'Y', 'status' => CampaignStatus::Draft]);

        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.store', $this->room), [
            'campaign_id' => $this->campaign->id, 'target_winner_count' => 2, 'target_campaign_id' => $target->id,
        ]);
        $session = SponsorDrawSession::query()->sole();
        $response->assertRedirect(route('admin.sponsor-draws.show', [$this->room, $session]));
        $this->assertSame(SponsorDrawStatus::Ready, $session->status);
        $this->assertSame($target->id, $session->target_campaign_id);
        $this->assertTrue($session->link_expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));

        $this->actingAs($this->admin, 'admin')->get(route('admin.sponsor-draws.show', [$this->room, $session]))
            ->assertOk()->assertSee('Next week')->assertSee('data-sponsor-draw', false)->assertSee('data-sd-draw>', false);
        $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.store', $this->room), ['campaign_id' => $this->campaign->id, 'target_winner_count' => 1])
            ->assertRedirect();
        $this->assertNull(SponsorDrawSession::query()->latest('id')->first()->target_campaign_id);
    }

    public function test_members_of_the_campaign_see_names_only_others_are_refused(): void
    {
        ['token' => $token] = $this->create();
        $member = $this->members[0];

        $page = $this->actingAs($member->globalUser, 'web')->get($this->landing($token))->assertOk()->assertSee('Friday tea')->assertSee('Binh');
        $this->assertStringNotContainsString($this->members[1]->globalUser->email, $page->getContent());
        $this->assertStringNotContainsString('room_user_id', $page->getContent());
        $state = $this->actingAs($member->globalUser, 'web')->getJson(route('user.sponsor-draws.state', [$this->room, $token]))->assertOk()->json('data.state');
        $this->assertSame(['position', 'name', 'is_winner'], array_keys($state['eligible'][0]));

        // Declined the campaign: no access, no session data.
        CampaignParticipant::create(['campaign_id' => $this->campaign->id, 'room_user_id' => $this->members[1]->id, 'status' => CampaignParticipant::STATUS_DECLINED]);
        $this->actingAs($this->members[1]->globalUser, 'web')->get($this->landing($token))
            ->assertForbidden()->assertSee('data-sponsor-draw-unavailable="forbidden"', false)->assertDontSee('Friday tea');
        $this->actingAs($this->members[1]->globalUser, 'web')->getJson(route('user.sponsor-draws.state', [$this->room, $token]))->assertForbidden();

        // A member of another room opening this room's link never reaches the session.
        $outsider = $this->member('Outsider', 'active', 'active', Room::create(['name' => 'Other', 'slug' => 'other-room', 'status' => 'active']));
        $this->actingAs($outsider->globalUser, 'web')->get($this->landing($token))->assertDontSee('Friday tea');

        // Guests are sent to sign in.
        auth('web')->logout();
        $this->get($this->landing($token))->assertRedirect();
    }

    public function test_expired_link_returns_nothing_for_anyone_but_keeps_the_results(): void
    {
        ['session' => $session, 'token' => $token] = $this->create(1);
        app(SponsorDrawService::class)->draw($session, $this->admin);
        $this->travel(61)->minutes();

        $this->actingAs($this->members[0]->globalUser, 'web')->get($this->landing($token))
            ->assertStatus(410)->assertSee('data-sponsor-draw-unavailable="expired"', false)->assertDontSee('Friday tea');
        $this->actingAs($this->members[0]->globalUser, 'web')->getJson(route('user.sponsor-draws.state', [$this->room, $token]))->assertStatus(410)->assertJsonMissingPath('data');
        $this->actingAs($this->members[0]->globalUser, 'web')->getJson(route('user.sponsor-draws.socket-token', [$this->room, $token]))->assertStatus(410);
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.sponsor-draws.socket-token', [$this->room, $session]))->assertStatus(410);
        // The admin keeps the session and its results.
        $this->actingAs($this->admin, 'admin')->get(route('admin.sponsor-draws.show', [$this->room, $session]))->assertOk()->assertSee('Friday tea');
        $this->assertSame(1, SponsorDrawResult::query()->count());

        // A new link works, the old one stays dead.
        $newToken = app(SponsorDrawService::class)->reissueLink($session, $this->admin);
        $this->actingAs($this->members[0]->globalUser, 'web')->get($this->landing($newToken))->assertOk();
        $this->actingAs($this->members[0]->globalUser, 'web')->get($this->landing($token))->assertStatus(410);
    }

    public function test_backend_draws_unique_winners_up_to_the_target_then_locks(): void
    {
        ['session' => $session] = $this->create(3);
        $url = route('admin.sponsor-draws.draw', [$this->room, $session]);

        $names = [];
        for ($turn = 1; $turn <= 3; $turn++) {
            // Whatever the client sends, the backend ignores it and picks the winner itself.
            $names[] = $this->actingAs($this->admin, 'admin')->postJson($url, ['room_user_id' => $this->members[0]->id, 'winner' => 'An'])
                ->assertOk()->assertJsonPath('data.draw_order', $turn)->json('data.name');
        }
        $this->assertCount(3, array_unique($names));
        $this->assertSame(SponsorDrawStatus::Completed, $session->fresh()->status);
        $this->actingAs($this->admin, 'admin')->postJson($url)->assertUnprocessable();
        $this->assertSame(3, SponsorDrawResult::query()->count());

        // The order is the committed one: recomputable from the seed and the frozen snapshot.
        $session->refresh();
        $expected = app(SponsorDrawRandomizer::class)->winnerPositions((string) $session->seed_encrypted, $session->eligible_count, 3);
        $this->assertSame($expected, SponsorDrawResult::query()->orderBy('draw_order')->pluck('snapshot_position')->all());

        // One member can never win twice in a session, even bypassing the service.
        $this->expectException(\Illuminate\Database\QueryException::class);
        $first = SponsorDrawResult::query()->first();
        SponsorDrawResult::create(['sponsor_draw_session_id' => $session->id, 'room_user_id' => $first->room_user_id, 'snapshot_position' => 0, 'display_name_snapshot' => 'x', 'draw_order' => 9, 'drawn_at' => now()]);
    }

    public function test_only_room_admins_can_draw_and_members_only_watch(): void
    {
        ['session' => $session, 'token' => $token] = $this->create(1);
        $url = route('admin.sponsor-draws.draw', [$this->room, $session]);

        $this->actingAs($this->members[0]->globalUser, 'web')->postJson($url)->assertUnauthorized();
        $otherAdmin = Admin::create(['name' => 'Other admin', 'email' => 'other-admin@example.test', 'password' => 'password123', 'status' => 'active']);
        $this->actingAs($otherAdmin, 'admin')->postJson($url)->assertForbidden();
        // A session is only reachable through its own room.
        $otherRoom = Room::create(['name' => 'Other', 'slug' => 'other-room', 'status' => 'active']);
        $otherAdmin->rooms()->attach($otherRoom);
        $this->actingAs($otherAdmin, 'admin')->postJson(route('admin.sponsor-draws.draw', [$otherRoom, $session]))->assertNotFound();
        $this->assertSame(0, SponsorDrawResult::query()->count());
        // The landing page has no draw control.
        $this->actingAs($this->members[0]->globalUser, 'web')->get($this->landing($token))->assertOk()->assertDontSee('data-sd-draw>', false);
    }

    public function test_draw_is_refused_once_the_link_expired(): void
    {
        ['session' => $session] = $this->create(2);
        $this->travel(61)->minutes();

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.sponsor-draws.draw', [$this->room, $session]))
            ->assertUnprocessable()->assertJsonPath('errors.session.0', __('sponsor_draw.errors.link_expired'));
    }

    public function test_seed_stays_secret_until_drawing_is_over_and_matches_its_commitment(): void
    {
        ['session' => $session] = $this->create(2);
        $service = app(SponsorDrawService::class);
        $randomizer = app(SponsorDrawRandomizer::class);

        $this->assertNull($service->state($session)['proof']['seed']);
        $service->draw($session, $this->admin);
        $this->assertNull($service->state($session->fresh())['proof']['seed']);
        $service->draw($session, $this->admin);

        $proof = $service->state($session->fresh())['proof'];
        $this->assertNotNull($proof['seed']);
        $this->assertSame($proof['seed_commitment'], $randomizer->commitment($proof['seed'], $session->id, $proof['snapshot_hash']));
        $record = $service->proof($session->fresh());
        $this->assertTrue($record['snapshot_hash_valid']);
        $this->assertTrue($record['seed_matches_commitment']);
        $this->assertTrue($record['event_chain']['valid']);
    }

    public function test_randomizer_is_deterministic_unbiased_in_range_and_never_repeats(): void
    {
        $randomizer = new SponsorDrawRandomizer();
        $seed = str_repeat('ab', 32);

        $this->assertSame($randomizer->winnerPositions($seed, 50, 10), $randomizer->winnerPositions($seed, 50, 10));
        $positions = $randomizer->winnerPositions($seed, 7, 7);
        sort($positions);
        $this->assertSame(range(0, 6), $positions);
        $this->assertSame([0], $randomizer->winnerPositions($seed, 1, 1));
        $this->assertNotSame($randomizer->winnerPositions($seed, 50, 10), $randomizer->winnerPositions(str_repeat('cd', 32), 50, 10));
    }

    public function test_event_chain_detects_tampering(): void
    {
        ['session' => $session] = $this->create(1);
        app(SponsorDrawService::class)->draw($session, $this->admin);
        $log = app(SponsorDrawEventLog::class);
        $this->assertTrue($log->verify($session)['valid']);

        $event = DB::table('sponsor_draw_events')->where('sponsor_draw_session_id', $session->id)->where('type', 'draw.completed')->first();
        DB::table('sponsor_draw_events')->where('id', $event->id)->update(['payload' => json_encode(['winner' => 'Someone else'])]);

        $result = $log->verify($session);
        $this->assertFalse($result['valid']);
        $this->assertSame((int) $event->sequence, $result['broken_at']);
        // The model itself refuses to rewrite history.
        $this->expectException(\LogicException::class);
        \App\Models\SponsorDrawEvent::query()->first()->update(['type' => 'changed']);
    }

    public function test_events_are_published_after_commit_on_the_session_channel_only(): void
    {
        $published = [];
        $this->mock(RealtimeEmitter::class, function (MockInterface $mock) use (&$published): void {
            $mock->shouldReceive('emit')->andReturnUsing(function (string $event, int $roomId, array $payload, ?string $channel) use (&$published): bool {
                $published[] = compact('event', 'roomId', 'payload', 'channel');

                return true;
            });
        });
        ['session' => $session] = $this->create(1);
        $published = [];

        DB::transaction(function () use ($session, &$published): void {
            app(SponsorDrawService::class)->draw($session, $this->admin);
            $this->assertSame([], $published, 'Nothing is pushed before the transaction commits.');
        });

        $this->assertSame(['draw.started', 'draw.completed', 'session.completed'], array_column(array_column($published, 'payload'), 'type'));
        $this->assertSame(['draw:'.$session->id], array_values(array_unique(array_column($published, 'channel'))));
        $sequences = array_column(array_column($published, 'payload'), 'sequence');
        $this->assertSame(range($sequences[0], $sequences[0] + 2), $sequences);
        $this->assertStringNotContainsString('@', json_encode($published));

        // Replay after a sequence returns exactly the missed events.
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.sponsor-draws.state', [$this->room, $session, 'after' => $sequences[0]]))
            ->assertOk()->assertJsonCount(2, 'data.events');
    }

    public function test_viewer_socket_token_is_scoped_to_the_session_and_link(): void
    {
        ['session' => $session, 'token' => $token] = $this->create(1);

        $socketToken = $this->actingAs($this->members[0]->globalUser, 'web')->getJson(route('user.sponsor-draws.socket-token', [$this->room, $token]))
            ->assertOk()->json('data.token');
        $claims = json_decode(base64_decode(strtr(explode('.', $socketToken)[0], '-_', '+/')), true);

        $this->assertSame('draw_viewer', $claims['actor_type']);
        $this->assertSame($session->id, $claims['draw_session_id']);
        $this->assertArrayNotHasKey('room_ids', $claims);
        $this->assertLessThanOrEqual($session->link_expires_at->timestamp, $claims['exp']);
        $this->assertNull(app(SocketTokenService::class)->verify($socketToken), 'A viewer token is not a room-user token.');
    }

    public function test_confirmation_assigns_sponsors_and_notifies_each_channel_once(): void
    {
        $target = Campaign::create(['room_id' => $this->room->id, 'name' => 'Next week', 'restaurant' => 'Y', 'status' => CampaignStatus::Draft]);
        $chatwork = NotificationChannel::create(['room_id' => $this->room->id, 'type' => 'chatwork', 'name' => 'Team CW', 'config_encrypted' => '{}', 'status' => NotificationChannel::STATUS_ENABLED]);
        $telegram = NotificationChannel::create(['room_id' => $this->room->id, 'type' => 'telegram', 'name' => 'Team TG', 'config_encrypted' => '{}', 'status' => NotificationChannel::STATUS_ENABLED]);
        NotificationChannel::create(['room_id' => $this->room->id, 'type' => 'slack', 'name' => 'Off', 'config_encrypted' => '{}', 'status' => 'disabled']);
        $sent = [];
        $failTelegram = true;
        $this->mock(RoomNotificationChannelDispatcher::class, function (MockInterface $mock) use (&$sent, &$failTelegram): void {
            $mock->shouldReceive('sendTo')->andReturnUsing(function (NotificationChannel $channel, array $payload) use (&$sent, &$failTelegram): void {
                if ($channel->type === 'telegram' && $failTelegram) {
                    throw new \RuntimeException('Telegram is down');
                }
                $sent[] = $channel->type;
                $this->assertStringContainsString('Friday tea', $payload['title']);
                $this->assertStringNotContainsString('@', $payload['message']);
            });
        });

        ['session' => $session] = $this->create(3, $target);
        $service = app(SponsorDrawService::class);
        $confirmUrl = route('admin.sponsor-draws.confirm', [$this->room, $session]);
        // Not before every sponsor is drawn.
        $this->actingAs($this->admin, 'admin')->post($confirmUrl)->assertSessionHasErrors('session');
        foreach (range(1, 3) as $ignored) {
            $service->draw($session, $this->admin);
        }

        $this->actingAs($this->admin, 'admin')->post($confirmUrl)->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertSame(SponsorDrawStatus::NotificationFailed, $session->status);
        $this->assertSame(['chatwork'], $sent);
        $this->assertSame(SponsorDrawNotification::STATUS_FAILED, SponsorDrawNotification::query()->where('notification_channel_id', $telegram->id)->value('status'));
        $this->assertSame(2, SponsorDrawNotification::query()->count());

        // The winners became the next campaign's sponsors, with shares adding up to 100%.
        $target->refresh();
        $this->assertSame(Campaign::SPONSOR_TYPE_FULL, $target->sponsor_type);
        $this->assertSame(100, (int) collect($target->sponsor_allocations)->sum('percentage'));
        $this->assertEqualsCanonicalizing(SponsorDrawResult::query()->pluck('room_user_id')->all(), collect($target->sponsor_allocations)->pluck('room_user_id')->all());

        // Confirming again sends nothing more; resending retries only the failed channel.
        $this->actingAs($this->admin, 'admin')->post($confirmUrl);
        $this->assertSame(['chatwork'], $sent);
        $failTelegram = false;
        $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.resend', [$this->room, $session]))->assertSessionHas('status');
        $this->assertSame(['chatwork', 'telegram'], $sent);
        $this->assertSame(SponsorDrawStatus::Notified, $session->fresh()->status);
        $this->assertSame(2, SponsorDrawNotification::query()->where('notification_channel_id', $telegram->id)->value('attempt_count'));
        $this->assertSame(1, SponsorDrawNotification::query()->where('notification_channel_id', $chatwork->id)->value('attempt_count'));
        $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.resend', [$this->room, $session]));
        $this->assertSame(['chatwork', 'telegram'], $sent);
        // A confirmed session can no longer be cancelled.
        $this->actingAs($this->admin, 'admin')->post(route('admin.sponsor-draws.cancel', [$this->room, $session]), ['reason' => 'Too late'])->assertSessionHasErrors('session');
    }

    public function test_cancellation_needs_a_reason_keeps_results_and_reveals_the_seed(): void
    {
        ['session' => $session, 'token' => $token] = $this->create(2);
        app(SponsorDrawService::class)->draw($session, $this->admin);
        $url = route('admin.sponsor-draws.cancel', [$this->room, $session]);

        $this->actingAs($this->admin, 'admin')->post($url, [])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin, 'admin')->post($url, ['reason' => 'Wrong campaign'])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame(SponsorDrawStatus::Cancelled, $session->status);
        $this->assertSame(1, SponsorDrawResult::query()->count());
        $this->assertNotNull($session->seed_revealed_at);
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.sponsor-draws.draw', [$this->room, $session]))->assertUnprocessable();
        $this->actingAs($this->members[0]->globalUser, 'web')->getJson(route('user.sponsor-draws.state', [$this->room, $token]))
            ->assertOk()->assertJsonPath('data.state.status', 'cancelled');
    }

    public function test_pages_have_no_fairness_section_and_cancel_opens_a_reason_modal(): void
    {
        ['session' => $session, 'token' => $token] = $this->create(1);
        $show = route('admin.sponsor-draws.show', [$this->room, $session]);

        $this->actingAs($this->members[0]->globalUser, 'web')->get($this->landing($token))
            ->assertOk()->assertDontSee('sd-proof', false)->assertDontSee('data-sd-cancel-open', false);
        $this->actingAs($this->admin, 'admin')->get($show)
            ->assertOk()->assertDontSee('sd-proof', false)
            ->assertSee('data-sd-cancel-open', false)->assertSee('data-sd-cancel-modal', false)->assertDontSee('data-open-on-load', false);

        // A missing reason brings the admin back with the modal open on the error.
        $this->actingAs($this->admin, 'admin')->from($show)->post(route('admin.sponsor-draws.cancel', [$this->room, $session]), ['reason' => ''])
            ->assertRedirect($show)->assertSessionHasErrors('reason');
        $this->actingAs($this->admin, 'admin')->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['reason' => 'Required']))])
            ->get($show)->assertSee('data-open-on-load', false)->assertSee('Required');
        $this->assertSame(SponsorDrawStatus::Ready, $session->fresh()->status);

        // Once cancelled there is nothing left to cancel.
        app(SponsorDrawService::class)->cancel($session, $this->admin, 'Wrong campaign');
        $this->actingAs($this->admin, 'admin')->get($show)->assertDontSee('data-sd-cancel-open', false)->assertDontSee('data-sd-cancel-modal', false);
    }

    public function test_admin_pages_and_proof_download(): void
    {
        ['session' => $session] = $this->create(1);
        app(SponsorDrawService::class)->draw($session, $this->admin);

        $this->actingAs($this->admin, 'admin')->get(route('admin.sponsor-draws.index', $this->room))
            ->assertOk()->assertSee('Friday tea')->assertSee(route('admin.sponsor-draws.show', [$this->room, $session]), false)->assertSee('data-nav-sponsor-draws', false);
        // Times are shown in the room's timezone, and the stage formats live times with the same zone.
        $this->room->update(['timezone' => 'Asia/Tokyo']);
        $this->actingAs($this->admin, 'admin')->get(route('admin.sponsor-draws.show', [$this->room, $session]))
            ->assertSee($session->link_expires_at->copy()->setTimezone('Asia/Tokyo')->format('H:i d/m/Y'))
            ->assertSee('&quot;timezone&quot;:&quot;Asia/Tokyo&quot;', false);
        $proof = $this->actingAs($this->admin, 'admin')->get(route('admin.sponsor-draws.proof', [$this->room, $session]))->assertOk();
        $this->assertStringContainsString('attachment', (string) $proof->headers->get('Content-Disposition'));
        $this->assertTrue($proof->json('event_chain.valid'));
        $this->assertCount(5, $proof->json('snapshot'));
    }

    private function assertValidationFails(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
