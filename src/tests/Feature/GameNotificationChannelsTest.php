<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GameRoomStatus;
use App\Models\Game;
use App\Models\GameNotificationChannel;
use App\Models\GameRoom;
use App\Models\GameRuleSet;
use App\Models\GlobalUser;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Utilities area: the renamed /me/utilities routes, the owner's notification channels and the delivery of a game
 * room's final leaderboard (automatically on finish, and again on demand).
 */
class GameNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:ABC-secret_token';

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameSeeder::class);
        $this->game = Game::where('slug', Game::SLUG_FUN)->firstOrFail();
    }

    public function test_old_games_links_redirect_permanently_to_utilities(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'web')->get('/me/games')->assertStatus(301)->assertRedirect('/me/utilities');
        $this->actingAs($user, 'web')->get('/me/games/'.$this->game->slug)->assertStatus(301)->assertRedirect('/me/utilities/'.$this->game->slug);
        $this->actingAs($user, 'web')->get(route('user.me.utilities'))->assertOk()
            ->assertSee(__('games.page_title'))->assertSee(route('user.me.utilities.channels.index'), false);
        $this->assertSame('/me/utilities/'.$this->game->slug, parse_url(route('user.me.utilities.show', $this->game), PHP_URL_PATH));
    }

    public function test_owner_creates_channel_with_encrypted_credentials_never_shown_again(): void
    {
        $user = $this->user();
        $channel = $this->channel($user);

        $this->assertSame('telegram', $channel->type);
        $this->assertStringNotContainsString(self::BOT_TOKEN, (string) DB::table('game_notification_channels')->value('config_encrypted'));
        $this->assertSame(['bot_token' => self::BOT_TOKEN, 'chat_id' => '-100123'], $channel->config());

        $this->actingAs($user, 'web')->get(route('user.me.utilities.channels.edit', $channel))
            ->assertOk()->assertDontSee(self::BOT_TOKEN)->assertSee('-100123')->assertSee(__('games.channels.keep_secret'));
        $this->actingAs($user, 'web')->get(route('user.me.utilities.channels.index'))->assertOk()
            ->assertSee('Nhóm cờ')->assertDontSee(self::BOT_TOKEN);

        // A blank secret keeps the stored one; plain settings are replaced.
        $this->actingAs($user, 'web')->put(route('user.me.utilities.channels.update', $channel), [
            'name' => 'Nhóm cờ mới', 'type' => 'telegram', 'is_active' => '1', 'config' => ['bot_token' => '', 'chat_id' => '-100999'],
        ])->assertRedirect(route('user.me.utilities.channels.index'));
        $this->assertSame(['bot_token' => self::BOT_TOKEN, 'chat_id' => '-100999'], $channel->refresh()->config());
        $this->assertSame('Nhóm cờ mới', $channel->name);
    }

    public function test_channel_needs_every_setting_of_its_type_and_a_safe_url(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'web')->post(route('user.me.utilities.channels.store'), [
            'name' => 'Thiếu', 'type' => 'telegram', 'config' => ['bot_token' => self::BOT_TOKEN],
        ])->assertSessionHasErrors('config.chat_id');
        $this->actingAs($user, 'web')->post(route('user.me.utilities.channels.store'), [
            'name' => 'Nội bộ', 'type' => 'webhook', 'config' => ['webhook_url' => 'http://127.0.0.1/hook'],
        ])->assertSessionHasErrors('config.webhook_url');
        $this->actingAs($user, 'web')->post(route('user.me.utilities.channels.store'), [
            'name' => 'Loại lạ', 'type' => 'email', 'config' => [],
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('game_notification_channels', 0);
    }

    public function test_only_the_owner_manages_or_uses_a_channel(): void
    {
        $owner = $this->user();
        $other = $this->user('other@company.com');
        $channel = $this->channel($owner);

        $this->actingAs($other, 'web')->get(route('user.me.utilities.channels.edit', $channel))->assertForbidden();
        $this->actingAs($other, 'web')->put(route('user.me.utilities.channels.update', $channel), [
            'name' => 'Hack', 'type' => 'telegram', 'config' => ['bot_token' => '1:x', 'chat_id' => '1'],
        ])->assertForbidden();
        $this->actingAs($other, 'web')->delete(route('user.me.utilities.channels.destroy', $channel))->assertForbidden();
        $this->actingAs($other, 'web')->post(route('user.me.utilities.channels.test', $channel))->assertForbidden();

        // Another user's channel cannot be picked for a room.
        $ruleSet = $this->ruleSet($other);
        $this->actingAs($other, 'web')->post(route('user.me.utilities.rooms.store', $this->game), [
            'name' => 'Bàn', 'players' => ['An', 'Bình'], 'rule_set_id' => $ruleSet->id, 'notification_channel_id' => $channel->id,
        ])->assertSessionHasErrors('notification_channel_id');
        $this->assertSame(0, GameRoom::count());
        $this->assertSame('Nhóm cờ', $channel->refresh()->name);
    }

    public function test_finishing_a_room_sends_the_final_leaderboard_to_its_channel(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $owner = $this->user();
        $channel = $this->channel($owner);
        $room = $this->startedRoom($owner, $channel);
        $this->assertSame((int) $channel->id, (int) $room->game_notification_channel_id);
        $this->record($owner, $room, 'Bình', ['An']);

        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.finish', [$this->game, $room]))
            ->assertRedirect()->assertSessionHas('status', __('games.flash.finished_and_notified'));

        $room->refresh();
        $this->assertSame(GameRoomStatus::Finished, $room->status);
        $this->assertNotNull($room->results_notified_at);
        $this->assertSame(1, $this->telegramRequests());
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'bot'.self::BOT_TOKEN.'/sendMessage')
            && $request['chat_id'] === '-100123'
            && str_contains($request['text'], 'Bàn cuối tuần')
            && str_contains($request['text'], '1. Bình')
            && str_contains($request['text'], '2. Chi')
            && str_contains($request['text'], '3. An'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'game_room.results_notified', 'target_id' => $room->id]);
    }

    public function test_failed_delivery_still_finishes_and_results_can_be_sent_again(): void
    {
        Http::fakeSequence('api.telegram.org/*')->push(['ok' => false], 500)->push(['ok' => true]);
        $owner = $this->user();
        $room = $this->startedRoom($owner, $this->channel($owner));

        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.finish', [$this->game, $room]))
            ->assertRedirect()->assertSessionHasErrors('channel');
        $this->assertSame(GameRoomStatus::Finished, $room->refresh()->status);
        $this->assertNull($room->results_notified_at);

        $this->actingAs($owner, 'web')->get(route('user.me.utilities.rooms.show', [$this->game, $room]))
            ->assertOk()->assertSee('data-resend-results', false)->assertSee(__('games.room.results_not_sent'));
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))
            ->assertRedirect()->assertSessionHas('status', __('games.flash.results_sent'));
        $this->assertNotNull($room->refresh()->results_notified_at);
        $this->assertSame(2, $this->telegramRequests());
    }

    public function test_room_list_offers_resending_results_of_finished_rooms_with_a_channel(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $owner = $this->user();
        $playing = $this->startedRoom($owner, $this->channel($owner));
        $listUrl = route('user.me.utilities.show', $this->game);
        $resendUrl = route('user.me.utilities.rooms.results.notify', [$this->game, $playing]);

        $this->actingAs($owner, 'web')->get($listUrl)->assertOk()->assertDontSee('data-list-resend-results', false);

        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.finish', [$this->game, $playing]))->assertRedirect();
        $this->actingAs($owner, 'web')->get($listUrl)->assertOk()
            ->assertSee('data-list-resend-results', false)->assertSee($resendUrl, false)->assertSee('data-confirm-dialog', false);

        $this->actingAs($owner, 'web')->from($listUrl)->post($resendUrl)
            ->assertRedirect($listUrl)->assertSessionHas('status', __('games.flash.results_sent'));
        $this->assertSame(2, $this->telegramRequests());
    }

    public function test_results_are_sent_only_for_a_finished_room_with_an_enabled_channel(): void
    {
        Http::fake();
        $owner = $this->user();
        $other = $this->user('other@company.com');
        $room = $this->startedRoom($owner, null);

        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))
            ->assertSessionHasErrors('channel');
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.finish', [$this->game, $room]))
            ->assertSessionHas('status', __('games.flash.finished'));
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))
            ->assertSessionHasErrors('channel');
        $this->assertSame(0, $this->telegramRequests());

        // A channel can still be chosen after the room finished, then the results sent.
        $channel = $this->channel($owner);
        $this->actingAs($other, 'web')->put(route('user.me.utilities.rooms.notification-channel.update', [$this->game, $room]), ['notification_channel_id' => $channel->id])
            ->assertForbidden();
        $this->actingAs($owner, 'web')->put(route('user.me.utilities.rooms.notification-channel.update', [$this->game, $room]), ['notification_channel_id' => $channel->id])
            ->assertSessionHas('status', __('games.flash.room_channel_updated'));
        $this->actingAs($other, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))->assertForbidden();
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))
            ->assertSessionHas('status', __('games.flash.results_sent'));
        $this->assertSame(1, $this->telegramRequests());

        // A disabled channel no longer receives anything; deleting it detaches the room.
        $channel->update(['is_active' => false]);
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.results.notify', [$this->game, $room]))
            ->assertSessionHasErrors('channel');
        $this->actingAs($owner, 'web')->delete(route('user.me.utilities.channels.destroy', $channel))->assertRedirect();
        $this->assertNull($room->refresh()->game_notification_channel_id);
        $this->assertSame(1, $this->telegramRequests());
    }

    public function test_clone_keeps_the_channel_of_the_source_room(): void
    {
        $owner = $this->user();
        $channel = $this->channel($owner);
        $room = $this->startedRoom($owner, $channel);

        $this->actingAs($owner, 'web')->get(route('user.me.utilities.rooms.create', ['game' => $this->game->slug, 'from' => $room->id]))
            ->assertOk()
            ->assertSee('name="notification_channel_id" value="'.$channel->id.'" checked', false);
    }

    /** Requests sent to Telegram (realtime gateway pushes go through the same HTTP client and are ignored here). */
    private function telegramRequests(): int
    {
        return Http::recorded(static fn (HttpRequest $request): bool => str_contains($request->url(), 'api.telegram.org'))->count();
    }

    private function user(string $email = 'owner@company.com'): GlobalUser
    {
        return GlobalUser::create([
            'name' => 'Owner '.$email,
            'normalized_name' => strtoupper('Owner '.$email),
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function channel(GlobalUser $owner): GameNotificationChannel
    {
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.channels.store'), [
            'name' => 'Nhóm cờ', 'type' => 'telegram', 'is_active' => '1',
            'config' => ['bot_token' => self::BOT_TOKEN, 'chat_id' => '-100123'],
        ])->assertRedirect(route('user.me.utilities.channels.index'))->assertSessionHasNoErrors();

        return GameNotificationChannel::where('owner_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function ruleSet(GlobalUser $owner): GameRuleSet
    {
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rule-sets.store', $this->game), [
            'name' => 'Luật chuẩn', 'items' => [['name' => 'Thắng thường', 'points' => 1, 'is_active' => '1']],
        ])->assertRedirect();

        return GameRuleSet::where('owner_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function startedRoom(GlobalUser $owner, ?GameNotificationChannel $channel): GameRoom
    {
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.store', $this->game), [
            'name' => 'Bàn cuối tuần', 'players' => ['An', 'Bình', 'Chi'], 'rule_set_id' => $this->ruleSet($owner)->id,
            'notification_channel_id' => $channel?->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $room = GameRoom::where('owner_id', $owner->id)->latest('id')->firstOrFail();
        $this->actingAs($owner, 'web')->post(route('user.me.utilities.rooms.start', [$this->game, $room]))->assertRedirect();

        return $room->refresh();
    }

    private function record(GlobalUser $owner, GameRoom $room, string $winner, array $losers): void
    {
        $players = $room->players()->pluck('id', 'display_name');
        $this->actingAs($owner, 'web')->postJson(route('user.me.utilities.rooms.results.store', [$this->game, $room]), [
            'rule_item_id' => $room->ruleVersion()->firstOrFail()->items()->value('id'),
            'winner_id' => $players[$winner],
            'loser_ids' => array_map(static fn (string $name): int => (int) $players[$name], $losers),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSuccessful();
    }
}
