<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameRoomStatus;
use App\Exceptions\GameActionException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\GameRound;
use App\Models\GameRuleSet;
use App\Models\GlobalUser;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lifecycle of a game room: create (waiting), start (playing, first round), new rounds, finish; players; public link.
 */
class GameRoomService
{
    /** Bounds of the number of players of a room. */
    public const MIN_PLAYERS = 2;
    public const MAX_PLAYERS = 20;
    public const DEFAULT_PLAYERS = 4;

    /** Owner's list filters. */
    public const FILTER_ACTIVE = 'active';
    public const FILTER_FINISHED = 'finished';

    /** Length of the public link token. */
    private const TOKEN_LENGTH = 40;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Rooms the owner created for a game, most recently active first.
     *
     * @param Game $game Game.
     * @param GlobalUser $owner Owner.
     * @param string|null $filter FILTER_ACTIVE, FILTER_FINISHED or null for all.
     * @param int $perPage Page size.
     * @return LengthAwarePaginator<int, GameRoom> Rooms with their active player count.
     */
    public function listForOwner(Game $game, GlobalUser $owner, ?string $filter, int $perPage = 20): LengthAwarePaginator
    {
        return GameRoom::query()
            ->where('game_id', $game->id)
            ->where('owner_id', $owner->id)
            ->when($filter === self::FILTER_ACTIVE, fn ($query) => $query->where('status', '!=', GameRoomStatus::Finished->value))
            ->when($filter === self::FILTER_FINISHED, fn ($query) => $query->where('status', GameRoomStatus::Finished->value))
            ->withCount(['players' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a waiting room applying the current version of the rule set.
     *
     * @param Game $game Game.
     * @param GlobalUser $owner Owner.
     * @param string $name Room name.
     * @param array<int, string|null> $playerNames Player names in order; a blank name gets a numbered default.
     * @param GameRuleSet $ruleSet Rule set of the owner for this game.
     * @return GameRoom Created room.
     *
     * @throws GameActionException When the rule set does not belong to the game or the player count is out of bounds.
     */
    public function create(Game $game, GlobalUser $owner, string $name, array $playerNames, GameRuleSet $ruleSet): GameRoom
    {
        if ((int) $ruleSet->game_id !== (int) $game->id || $ruleSet->current_version_id === null) {
            throw new GameActionException(__('games.errors.rule_set_invalid'));
        }
        $names = $this->normalizePlayerNames($playerNames);
        if (count($names) < self::MIN_PLAYERS || count($names) > self::MAX_PLAYERS) {
            throw new GameActionException(__('games.errors.player_count', ['min' => self::MIN_PLAYERS, 'max' => self::MAX_PLAYERS]));
        }

        return DB::transaction(function () use ($game, $owner, $name, $names, $ruleSet): GameRoom {
            $room = GameRoom::create([
                'game_id' => $game->id,
                'owner_id' => $owner->id,
                'name' => $name,
                'status' => GameRoomStatus::Waiting,
                'game_rule_set_id' => $ruleSet->id,
                'game_rule_version_id' => $ruleSet->current_version_id,
                'public_token' => $this->newToken(),
                'last_activity_at' => now(),
            ]);
            foreach ($names as $index => $playerName) {
                $room->players()->create(['display_name' => $playerName, 'sort_order' => $index + 1]);
            }
            $this->audit->record('game_room.created', 'game_room', (int) $room->id, null, [], ['name' => $name, 'players' => $names]);

            return $room;
        });
    }

    /**
     * Start a waiting room: it becomes playing and its first round opens.
     *
     * @param GameRoom $room Room.
     * @return void
     *
     * @throws GameActionException When the room is not waiting or has fewer than two active players.
     */
    public function start(GameRoom $room): void
    {
        DB::transaction(function () use ($room): void {
            $locked = $this->lock($room);
            if ($locked->status !== GameRoomStatus::Waiting) {
                throw new GameActionException(__('games.errors.not_waiting'));
            }
            if ($locked->players()->where('is_active', true)->count() < self::MIN_PLAYERS) {
                throw new GameActionException(__('games.errors.not_enough_players', ['min' => self::MIN_PLAYERS]));
            }
            $locked->update(['status' => GameRoomStatus::Playing, 'started_at' => now(), 'last_activity_at' => now()]);
            GameRound::create(['game_room_id' => $locked->id, 'number' => 1]);
            $this->audit->record('game_room.started', 'game_room', (int) $locked->id);
        });
    }

    /**
     * Open the next round of a playing room.
     *
     * @param GameRoom $room Room.
     * @return GameRound New round.
     *
     * @throws GameActionException When the room is not playing.
     */
    public function openRound(GameRoom $room): GameRound
    {
        return DB::transaction(function () use ($room): GameRound {
            $locked = $this->lock($room);
            $this->assertPlaying($locked);
            $number = (int) $locked->rounds()->max('number') + 1;
            $round = GameRound::create(['game_room_id' => $locked->id, 'number' => $number]);
            $locked->update(['last_activity_at' => now()]);
            $this->audit->record('game_round.opened', 'game_room', (int) $locked->id, null, [], ['round' => $number]);

            return $round;
        });
    }

    /**
     * Finish a room by hand. A finished room accepts no more results, rounds or corrections.
     *
     * @param GameRoom $room Room.
     * @return void
     *
     * @throws GameActionException When the room is already finished.
     */
    public function finish(GameRoom $room): void
    {
        DB::transaction(function () use ($room): void {
            $locked = $this->lock($room);
            if ($locked->status === GameRoomStatus::Finished) {
                throw new GameActionException(__('games.errors.finished'));
            }
            $locked->update(['status' => GameRoomStatus::Finished, 'finished_at' => now(), 'last_activity_at' => now()]);
            $this->audit->record('game_room.finished', 'game_room', (int) $locked->id);
        });
    }

    /**
     * Make the room apply the current version of its rule set. Results already recorded keep their snapshot.
     *
     * @param GameRoom $room Room.
     * @return void
     *
     * @throws GameActionException When the room is finished or already applies the current version.
     */
    public function applyLatestRuleVersion(GameRoom $room): void
    {
        DB::transaction(function () use ($room): void {
            $locked = $this->lock($room);
            if ($locked->status === GameRoomStatus::Finished) {
                throw new GameActionException(__('games.errors.finished'));
            }
            $latest = $locked->ruleSet()->value('current_version_id');
            if ($latest === null || (int) $latest === (int) $locked->game_rule_version_id) {
                throw new GameActionException(__('games.errors.rule_version_current'));
            }
            $before = (int) $locked->game_rule_version_id;
            $locked->update(['game_rule_version_id' => $latest, 'last_activity_at' => now()]);
            $this->audit->record('game_room.rule_version_applied', 'game_room', (int) $locked->id, null, ['rule_version_id' => $before], ['rule_version_id' => (int) $latest]);
        });
    }

    /**
     * Issue a new public link token; the previous link stops working.
     *
     * @param GameRoom $room Room.
     * @return void
     */
    public function regeneratePublicToken(GameRoom $room): void
    {
        $room->update(['public_token' => $this->newToken()]);
        $this->audit->record('game_room.public_link_regenerated', 'game_room', (int) $room->id);
    }

    /**
     * Revoke the public link.
     *
     * @param GameRoom $room Room.
     * @return void
     */
    public function revokePublicToken(GameRoom $room): void
    {
        $room->update(['public_token' => null]);
        $this->audit->record('game_room.public_link_revoked', 'game_room', (int) $room->id);
    }

    /**
     * Add a player to a room that is not finished.
     *
     * @param GameRoom $room Room.
     * @param string $displayName Display name.
     * @return GamePlayer New player.
     *
     * @throws GameActionException When the room is finished, full, or the name is taken.
     */
    public function addPlayer(GameRoom $room, string $displayName): GamePlayer
    {
        return DB::transaction(function () use ($room, $displayName): GamePlayer {
            $locked = $this->lock($room);
            $this->assertNotFinished($locked);
            if ($locked->players()->where('is_active', true)->count() >= self::MAX_PLAYERS) {
                throw new GameActionException(__('games.errors.player_count', ['min' => self::MIN_PLAYERS, 'max' => self::MAX_PLAYERS]));
            }
            $this->assertNameAvailable($locked, $displayName, null);
            $player = $locked->players()->create([
                'display_name' => $displayName,
                'sort_order' => (int) $locked->players()->max('sort_order') + 1,
            ]);
            $this->audit->record('game_player.added', 'game_player', (int) $player->id, null, [], ['display_name' => $displayName], ['game_room_id' => $locked->id]);

            return $player;
        });
    }

    /**
     * Rename a player or (de)activate them. History references the player id, so it follows the new name; an inactive
     * player keeps their results but cannot be picked for new ones.
     *
     * @param GamePlayer $player Player.
     * @param string $displayName New display name.
     * @param bool $isActive Whether the player still plays.
     * @return void
     *
     * @throws GameActionException When the room is finished, the name is taken or too many players would be active.
     */
    public function updatePlayer(GamePlayer $player, string $displayName, bool $isActive): void
    {
        DB::transaction(function () use ($player, $displayName, $isActive): void {
            $room = $this->lock($player->gameRoom);
            $this->assertNotFinished($room);
            $this->assertNameAvailable($room, $displayName, (int) $player->id);
            if ($isActive && ! $player->is_active && $room->players()->where('is_active', true)->count() >= self::MAX_PLAYERS) {
                throw new GameActionException(__('games.errors.player_count', ['min' => self::MIN_PLAYERS, 'max' => self::MAX_PLAYERS]));
            }
            $before = ['display_name' => $player->display_name, 'is_active' => $player->is_active];
            $player->update(['display_name' => $displayName, 'is_active' => $isActive]);
            $this->audit->record('game_player.updated', 'game_player', (int) $player->id, null, $before, ['display_name' => $displayName, 'is_active' => $isActive], ['game_room_id' => $room->id]);
        });
    }

    /**
     * Re-read the room with a row lock so concurrent actions are serialized.
     *
     * @param GameRoom $room Room.
     * @return GameRoom Locked, fresh room.
     */
    public function lock(GameRoom $room): GameRoom
    {
        return GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param GameRoom $room Room.
     * @return void
     *
     * @throws GameActionException When the room is not playing.
     */
    public function assertPlaying(GameRoom $room): void
    {
        if ($room->status === GameRoomStatus::Playing) {
            return;
        }

        throw new GameActionException($room->status === GameRoomStatus::Finished ? __('games.errors.finished') : __('games.errors.not_playing'));
    }

    /**
     * @param GameRoom $room Room.
     * @return void
     *
     * @throws GameActionException When the room is finished.
     */
    private function assertNotFinished(GameRoom $room): void
    {
        if ($room->status === GameRoomStatus::Finished) {
            throw new GameActionException(__('games.errors.finished'));
        }
    }

    /**
     * Display names are unique in a room (case-insensitive) so the leaderboard stays readable.
     *
     * @param GameRoom $room Room.
     * @param string $displayName Wanted name.
     * @param int|null $exceptPlayerId Player being renamed.
     * @return void
     *
     * @throws GameActionException When another player already has the name.
     */
    private function assertNameAvailable(GameRoom $room, string $displayName, ?int $exceptPlayerId): void
    {
        $wanted = mb_strtolower(trim($displayName));
        $taken = $room->players()
            ->when($exceptPlayerId !== null, fn ($query) => $query->whereKeyNot($exceptPlayerId))
            ->pluck('display_name')
            ->contains(static fn (string $name): bool => mb_strtolower(trim($name)) === $wanted);
        if ($taken) {
            throw new GameActionException(__('games.errors.player_name_taken'));
        }
    }

    /**
     * Trim the names, number the blank ones and refuse duplicates.
     *
     * @param array<int, string|null> $playerNames Raw names.
     * @return array<int, string> Final names in order.
     *
     * @throws GameActionException When two players share a name.
     */
    private function normalizePlayerNames(array $playerNames): array
    {
        $names = [];
        foreach (array_values($playerNames) as $index => $name) {
            $name = trim((string) $name);
            $names[] = $name !== '' ? $name : __('games.create.default_player', ['number' => $index + 1]);
        }
        $lower = array_map(static fn (string $name): string => mb_strtolower($name), $names);
        if (count(array_unique($lower)) !== count($lower)) {
            throw new GameActionException(__('games.errors.player_name_taken'));
        }

        return $names;
    }

    /**
     * @return string Random URL-safe token.
     */
    private function newToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }
}
