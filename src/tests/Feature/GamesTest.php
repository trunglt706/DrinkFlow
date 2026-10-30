<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GameResultStatus;
use App\Enums\GameRoomStatus;
use App\Enums\GameScoreTransactionType;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameRoom;
use App\Models\GameRuleSet;
use App\Models\GameScoreTransaction;
use App\Models\GlobalUser;
use App\Services\Game\GameStatisticsService;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameSeeder::class);
        $this->game = Game::where('slug', Game::SLUG_FUN)->firstOrFail();
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

    private function ruleSet(GlobalUser $owner, array $items = [['name' => 'Thắng thường', 'points' => 1, 'is_active' => '1'], ['name' => 'Thắng lớn', 'points' => 3, 'is_active' => '1']]): GameRuleSet
    {
        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rule-sets.store', $this->game), ['name' => 'Luật chuẩn', 'items' => $items])
            ->assertRedirect(route('user.me.games.rule-sets.index', $this->game));

        return GameRuleSet::where('owner_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function room(GlobalUser $owner, GameRuleSet $ruleSet, array $players = ['An', 'Bình', 'Chi', 'Dũng']): GameRoom
    {
        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rooms.store', $this->game), ['name' => 'Bàn cuối tuần', 'players' => $players, 'rule_set_id' => $ruleSet->id])
            ->assertRedirect();

        return GameRoom::where('owner_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function startedRoom(GlobalUser $owner): GameRoom
    {
        $room = $this->room($owner, $this->ruleSet($owner));
        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.start', [$this->game, $room]))->assertRedirect();

        return $room->refresh();
    }

    private function record(GlobalUser $owner, GameRoom $room, string $item, string $winner, array $losers, ?string $key = null)
    {
        $players = $room->players()->pluck('id', 'display_name');
        $itemId = $room->ruleVersion()->firstOrFail()->items()->where('name', $item)->value('id');

        return $this->actingAs($owner, 'web')->postJson(route('user.me.games.rooms.results.store', [$this->game, $room]), [
            'rule_item_id' => $itemId,
            'winner_id' => $players[$winner],
            'loser_ids' => array_map(static fn (string $name): int => $players[$name], $losers),
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    public function test_me_dashboard_shows_games_banner_linking_to_games_list(): void
    {
        $this->actingAs($this->user(), 'web')->get('/me')
            ->assertOk()
            ->assertSee(__('games.banner.title'))
            ->assertSee(route('user.me.games'), false);
    }

    public function test_games_list_shows_seeded_game_and_guest_is_redirected(): void
    {
        $this->get(route('user.me.games'))->assertRedirect('/');

        $this->actingAs($this->user(), 'web')->get(route('user.me.games'))
            ->assertOk()
            ->assertSee('Game vui')
            ->assertSee(route('user.me.games.show', $this->game), false);
    }

    public function test_owner_creates_room_and_sees_it_in_history_while_others_cannot_open_it(): void
    {
        $owner = $this->user();
        $room = $this->room($owner, $this->ruleSet($owner));

        $this->assertSame(GameRoomStatus::Waiting, $room->status);
        $this->assertSame(4, $room->players()->count());
        $this->assertNotNull($room->public_token);

        $this->actingAs($owner, 'web')->get(route('user.me.games.show', $this->game))
            ->assertOk()->assertSee('Bàn cuối tuần')->assertSee(__('games.status.waiting'));
        $this->actingAs($owner, 'web')->get(route('user.me.games.rooms.show', [$this->game, $room]))
            ->assertOk()->assertSee('Bàn cuối tuần');

        $other = $this->user('other@company.com');
        $this->actingAs($other, 'web')->get(route('user.me.games.show', $this->game))->assertOk()->assertDontSee('Bàn cuối tuần');
        $this->actingAs($other, 'web')->get(route('user.me.games.rooms.show', [$this->game, $room]))->assertForbidden();
        $this->actingAs($other, 'web')->post(route('user.me.games.rooms.start', [$this->game, $room]))->assertForbidden();
    }

    public function test_room_cannot_use_another_owners_rule_set_and_blank_player_names_are_numbered(): void
    {
        $owner = $this->user();
        $foreign = $this->ruleSet($this->user('other@company.com'));

        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rooms.store', $this->game), ['name' => 'X', 'players' => ['A', 'B'], 'rule_set_id' => $foreign->id])
            ->assertSessionHasErrors('rule_set_id');

        $room = $this->room($owner, $this->ruleSet($owner), ['An', '', '']);
        $this->assertSame(['An', 'Người chơi 2', 'Người chơi 3'], $room->players()->pluck('display_name')->all());
    }

    public function test_start_opens_first_round_and_results_need_a_playing_room(): void
    {
        $owner = $this->user();
        $room = $this->room($owner, $this->ruleSet($owner));

        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertStatus(422);

        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.start', [$this->game, $room]))->assertRedirect();
        $room->refresh();
        $this->assertSame(GameRoomStatus::Playing, $room->status);
        $this->assertSame([1], $room->rounds()->pluck('number')->all());

        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertCreated();
    }

    public function test_result_points_come_from_rule_item_and_are_snapshotted(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);

        $this->record($owner, $room, 'Thắng lớn', 'An', ['Bình', 'Chi'])->assertCreated();

        $result = GameResult::firstOrFail();
        $this->assertSame(3, $result->points);
        $this->assertSame('Thắng lớn', $result->rule_item_name);
        $this->assertSame(1, $result->rule_version_number);
        $this->assertSame(2, $result->losers()->count());
        $this->assertSame(1, GameScoreTransaction::count());
        $this->assertSame(3, (int) GameScoreTransaction::sum('points'));
    }

    public function test_retry_with_same_idempotency_key_does_not_duplicate_result(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $key = (string) Str::uuid();

        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'], $key)->assertCreated()->assertJson(['replayed' => false]);
        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'], $key)->assertOk()->assertJson(['replayed' => true]);

        $this->assertSame(1, GameResult::count());
        $this->assertSame(1, GameScoreTransaction::count());
    }

    public function test_invalid_results_are_rejected(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $other = $this->startedRoom($this->user('other@company.com'));
        $players = $room->players()->pluck('id', 'display_name');
        $itemId = $room->ruleVersion()->firstOrFail()->items()->value('id');
        $url = route('user.me.games.rooms.results.store', [$this->game, $room]);
        $payload = fn (array $overrides): array => $overrides + ['rule_item_id' => $itemId, 'winner_id' => $players['An'], 'loser_ids' => [$players['Bình']], 'idempotency_key' => (string) Str::uuid()];

        // Winner in the loser list.
        $this->actingAs($owner, 'web')->postJson($url, $payload(['loser_ids' => [$players['An']]]))->assertStatus(422);
        // No loser.
        $this->actingAs($owner, 'web')->postJson($url, $payload(['loser_ids' => []]))->assertStatus(422);
        // Loser of another room.
        $this->actingAs($owner, 'web')->postJson($url, $payload(['loser_ids' => [$other->players()->value('id')]]))->assertStatus(422);
        // Rule item of another room's version.
        $this->actingAs($owner, 'web')->postJson($url, $payload(['rule_item_id' => $other->ruleVersion()->firstOrFail()->items()->value('id')]))->assertStatus(422);
        // Client-sent points are ignored.
        $this->actingAs($owner, 'web')->postJson($url, $payload(['points' => 999]))->assertCreated();

        $this->assertSame(1, GameResult::count());
        $this->assertSame(1, (int) GameResult::value('points'));
    }

    public function test_statistics_count_wins_losses_head_to_head_and_ranking(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);

        $this->record($owner, $room, 'Thắng lớn', 'An', ['Bình', 'Chi'])->assertCreated();
        $this->record($owner, $room, 'Thắng thường', 'Bình', ['An'])->assertCreated();
        $this->record($owner, $room, 'Thắng lớn', 'Chi', ['Dũng'])->assertCreated();

        $stats = app(GameStatisticsService::class)->dashboard($room);
        $board = collect($stats['leaderboard'])->keyBy('name');

        // An and Chi both have 3 points and 1 win: display name decides.
        $this->assertSame(['An', 'Chi', 'Bình', 'Dũng'], array_column($stats['leaderboard'], 'name'));
        $this->assertSame([3, 1, 1], [$board['An']['points'], $board['An']['wins'], $board['An']['losses']]);
        $this->assertSame([1, 1, 1], [$board['Bình']['points'], $board['Bình']['wins'], $board['Bình']['losses']]);
        $this->assertSame(1, $board['Chi']['losses']);

        $anVsBinh = collect($stats['headToHead'][$board['An']['player_id']])->firstWhere('opponent', 'Bình');
        $this->assertSame(['opponent' => 'Bình', 'wins' => 1, 'losses' => 1], $anVsBinh);
        $this->assertSame(3, $stats['summary']['results']);
    }

    public function test_void_keeps_history_adds_reversal_and_excludes_result_from_stats(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $this->record($owner, $room, 'Thắng lớn', 'An', ['Bình'])->assertCreated();
        $result = GameResult::firstOrFail();

        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rooms.results.void', [$this->game, $room, $result]), [])
            ->assertSessionHasErrors('reason');
        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rooms.results.void', [$this->game, $room, $result]), ['reason' => 'Ghi nhầm'])
            ->assertRedirect();

        $result->refresh();
        $this->assertSame(GameResultStatus::Voided, $result->status);
        $this->assertSame('Ghi nhầm', $result->void_reason);
        $this->assertSame(2, GameScoreTransaction::count());
        $this->assertSame(1, GameScoreTransaction::where('type', GameScoreTransactionType::Reversal->value)->count());

        $stats = app(GameStatisticsService::class)->dashboard($room);
        $an = collect($stats['leaderboard'])->firstWhere('name', 'An');
        $this->assertSame([0, 0], [$an['points'], $an['wins']]);
        $this->assertSame(0, $stats['summary']['results']);
        $this->assertCount(1, $stats['history']);

        // A second void is refused.
        $this->actingAs($owner, 'web')
            ->post(route('user.me.games.rooms.results.void', [$this->game, $room, $result]), ['reason' => 'Lần nữa'])
            ->assertSessionHas('error', __('games.errors.already_voided'));
    }

    public function test_rule_change_creates_version_applied_only_on_demand_and_old_snapshots_stay(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertCreated();
        $ruleSet = $room->ruleSet()->firstOrFail();

        $this->actingAs($owner, 'web')->put(route('user.me.games.rule-sets.update', [$this->game, $ruleSet]), [
            'name' => 'Luật chuẩn',
            'items' => [['name' => 'Thắng thường', 'points' => 2, 'is_active' => '1'], ['name' => 'Thắng lớn', 'points' => 3]],
        ])->assertRedirect();

        $ruleSet->refresh();
        $this->assertSame(2, $ruleSet->currentVersion->version);
        $this->assertSame(1, $room->refresh()->ruleVersion->version);
        $this->actingAs($owner, 'web')->get(route('user.me.games.rooms.show', [$this->game, $room]))
            ->assertSee(__('games.room.apply_version'));

        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.rule-version', [$this->game, $room]))->assertRedirect();
        $this->assertSame(2, $room->refresh()->ruleVersion->version);

        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertCreated();
        // The archived item of v2 can no longer be picked.
        $this->record($owner, $room, 'Thắng lớn', 'An', ['Bình'])->assertStatus(422);

        $this->assertSame([1, 2], GameResult::orderBy('id')->pluck('points')->all());
        $this->assertSame([1, 2], GameResult::orderBy('id')->pluck('rule_version_number')->all());
    }

    public function test_negative_points_need_the_switch(): void
    {
        $owner = $this->user();
        $this->actingAs($owner, 'web')->post(route('user.me.games.rule-sets.store', $this->game), [
            'name' => 'Phạt',
            'items' => [['name' => 'Phạt', 'points' => -1, 'is_active' => '1']],
        ])->assertSessionHasErrors('items.0.points');

        $this->actingAs($owner, 'web')->post(route('user.me.games.rule-sets.store', $this->game), [
            'name' => 'Phạt',
            'allow_negative_points' => '1',
            'items' => [['name' => 'Phạt', 'points' => -1, 'is_active' => '1']],
        ])->assertSessionHasNoErrors();
    }

    public function test_renaming_player_keeps_history_and_finished_room_rejects_results(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertCreated();
        $an = $room->players()->where('display_name', 'An')->firstOrFail();

        $this->actingAs($owner, 'web')
            ->patch(route('user.me.games.rooms.players.update', [$this->game, $room, $an]), ['display_name' => 'An Nguyễn', 'is_active' => '1'])
            ->assertRedirect();
        $this->assertSame($an->id, GameResult::firstOrFail()->winner_player_id);
        $this->assertSame('An Nguyễn', GameResult::firstOrFail()->winner->display_name);

        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.finish', [$this->game, $room]))->assertRedirect();
        $this->assertSame(GameRoomStatus::Finished, $room->refresh()->status);
        $this->record($owner, $room, 'Thắng thường', 'Bình', ['Chi'])->assertStatus(422);
    }

    public function test_public_page_shows_room_and_revoked_link_shows_clear_message(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $this->record($owner, $room, 'Thắng thường', 'An', ['Bình'])->assertCreated();
        $token = $room->public_token;
        $an = $room->players()->where('display_name', 'An')->value('id');

        auth('web')->logout();
        $this->get(route('public.games.rooms.show', $token))
            ->assertOk()
            ->assertSee('Bàn cuối tuần')
            ->assertSee(__('games.public.choose_name'))
            ->assertDontSee('owner@company.com')
            ->assertDontSee(__('games.void.action'));
        $this->get(route('public.games.rooms.show', ['token' => $token, 'player' => $an]))
            ->assertOk()
            ->assertSee(__('games.play.you', ['name' => 'An']))
            ->assertSee(__('games.play.record'))
            ->assertDontSee('data-play-new-round', false);

        $this->actingAs($owner, 'web')->delete(route('user.me.games.rooms.public-link.revoke', [$this->game, $room]))->assertRedirect();
        auth('web')->logout();
        $this->get(route('public.games.rooms.show', $token))
            ->assertNotFound()
            ->assertSee(__('games.public.invalid_title'));

        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.public-link.regenerate', [$this->game, $room]))->assertRedirect();
        $this->assertNotNull($room->refresh()->public_token);
        $this->assertNotSame($token, $room->public_token);
    }

    private function publicRecord(GameRoom $room, string $loser, ?string $key = null, ?string $item = 'Thắng thường')
    {
        $players = $room->players()->pluck('id', 'display_name');
        $itemId = $room->ruleVersion()->firstOrFail()->items()->where('name', $item)->value('id');

        return $this->postJson(route('public.games.rooms.results.store', $room->public_token), [
            'rule_item_id' => $itemId,
            'loser_id' => $players[$loser],
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    public function test_visitor_records_own_win_from_public_link(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        auth('web')->logout();
        $binh = $room->players()->where('display_name', 'Bình')->firstOrFail();

        // A player must be picked first.
        $this->publicRecord($room, 'An')->assertStatus(422)->assertJson(['message' => __('games.play.choose_first')]);

        $this->get(route('public.games.rooms.show', ['token' => $room->public_token, 'player' => $binh->id]))->assertOk();
        $key = (string) Str::uuid();
        $this->publicRecord($room, 'An', $key, 'Thắng lớn')->assertCreated();
        $this->publicRecord($room, 'An', $key, 'Thắng lớn')->assertOk()->assertJson(['replayed' => true]);

        $result = GameResult::firstOrFail();
        $this->assertSame($binh->id, $result->winner_player_id);
        $this->assertSame($binh->id, $result->recorded_by_player_id);
        $this->assertNull($result->recorded_by);
        $this->assertSame(3, $result->points);
        $this->assertSame(1, GameResult::count());

        // The visitor cannot name themselves as loser, nor send several losers.
        $this->publicRecord($room, 'Bình')->assertStatus(422);
        $players = $room->players()->pluck('id', 'display_name');
        $this->postJson(route('public.games.rooms.results.store', $room->public_token), [
            'rule_item_id' => $result->game_rule_item_id,
            'loser_ids' => [$players['An'], $players['Chi']],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors('loser_id');
        $this->assertSame(1, GameResult::count());
        $this->assertSame(1, $result->losers()->count());

        $this->get(route('public.games.rooms.show', $room->public_token))
            ->assertSee(__('games.history_list.recorded_by', ['name' => 'Bình']));
    }

    public function test_public_record_is_refused_for_inactive_player_finished_room_and_revoked_link(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $an = $room->players()->where('display_name', 'An')->firstOrFail();
        $this->actingAs($owner, 'web')
            ->patch(route('user.me.games.rooms.players.update', [$this->game, $room, $an]), ['display_name' => 'An'])
            ->assertRedirect();
        auth('web')->logout();

        $this->get(route('public.games.rooms.show', ['token' => $room->public_token, 'player' => $an->id]))
            ->assertOk()->assertSee(__('games.play.inactive_player'))->assertDontSee('data-play-record-open', false);
        $this->publicRecord($room, 'Bình')->assertStatus(422);

        $chi = $room->players()->where('display_name', 'Chi')->value('id');
        $this->get(route('public.games.rooms.show', ['token' => $room->public_token, 'player' => $chi]))->assertOk();
        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.finish', [$this->game, $room]))->assertRedirect();
        auth('web')->logout();
        // A finished room is closed: the public link no longer accepts anything.
        $this->publicRecord($room, 'Bình')->assertStatus(410);

        $token = $room->public_token;
        $this->actingAs($owner, 'web')->delete(route('user.me.games.rooms.public-link.revoke', [$this->game, $room]))->assertRedirect();
        auth('web')->logout();
        $this->postJson(route('public.games.rooms.results.store', $token), [
            'rule_item_id' => 1, 'loser_id' => 1, 'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertSame(0, GameResult::count());
    }

    public function test_visitor_records_several_results_in_a_round_one_submission_each(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $url = route('public.games.rooms.show', $room->public_token);
        $binh = $room->players()->where('display_name', 'Bình')->value('id');
        auth('web')->logout();
        $this->get(route('public.games.rooms.show', ['token' => $room->public_token, 'player' => $binh]))->assertOk();

        // Each submission is its own result (own key, one loser); the same round accepts several of them.
        $this->publicRecord($room, 'An')->assertCreated();
        $this->get($url)->assertOk()->assertSee('data-play-record-open', false);
        $this->publicRecord($room, 'Chi')->assertCreated();
        $this->publicRecord($room, 'An')->assertCreated();

        $results = GameResult::orderBy('id')->get();
        $this->assertCount(3, $results);
        $this->assertSame([1], $results->map(fn (GameResult $result): int => $result->round->number)->unique()->values()->all());
        $this->assertSame([1, 1, 1], $results->map(fn (GameResult $result): int => $result->losers()->count())->all());
        $this->assertSame(3, collect(app(GameStatisticsService::class)->dashboard($room)['leaderboard'])->firstWhere('name', 'Bình')['wins']);
    }

    public function test_public_page_has_share_qr_and_closes_with_room_or_game(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $url = route('public.games.rooms.show', $room->public_token);
        auth('web')->logout();

        $this->get($url)->assertOk()
            ->assertSee('data-play-share-open', false)
            ->assertSee('data-url="'.$url.'"', false)
            ->assertSee('qrcode.min.js', false);

        // Game turned off by a Superadmin: closed.
        $this->game->update(['is_active' => false]);
        $this->get($url)->assertStatus(410)->assertSee(__('games.public.closed_title'))->assertDontSee('Bàn cuối tuần');
        $this->game->update(['is_active' => true]);
        $this->get($url)->assertOk();

        // Room finished: closed, also for the owner; the owner still sees the history in /me.
        $this->actingAs($owner, 'web')->post(route('user.me.games.rooms.finish', [$this->game, $room]))->assertRedirect();
        $this->actingAs($owner, 'web')->get($url)->assertStatus(410)->assertSee(__('games.public.closed_desc'));
        $this->actingAs($owner, 'web')->get(route('user.me.games.rooms.show', [$this->game, $room]))
            ->assertOk()->assertSee(__('games.room.link_closed'));
    }

    public function test_only_signed_in_owner_sees_new_round_on_public_page(): void
    {
        $owner = $this->user();
        $room = $this->startedRoom($owner);
        $url = route('public.games.rooms.show', $room->public_token);

        $this->actingAs($owner, 'web')->get($url)->assertOk()
            ->assertSee('data-play-new-round', false)
            ->assertSee('data-play-back', false)
            ->assertSee(route('user.me.games.rooms.show', [$this->game, $room]), false)
            ->assertSee('data-play-language', false);
        $this->actingAs($owner, 'web')->from($url)->post(route('user.me.games.rooms.rounds.store', [$this->game, $room]))->assertRedirect($url);
        $this->assertSame(2, $room->rounds()->count());

        $this->actingAs($this->user('other@company.com'), 'web')->get($url)->assertOk()->assertDontSee('data-play-new-round', false)->assertDontSee('data-play-back', false);
        auth('web')->logout();
        $this->get($url)->assertOk()->assertDontSee('data-play-new-round', false)->assertDontSee('data-play-back', false);
    }
}
