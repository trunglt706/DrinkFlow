<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameResultStatus;
use App\Enums\GameScoreTransactionType;
use App\Exceptions\GameActionException;
use App\Models\GamePlayer;
use App\Models\GameResult;
use App\Models\GameRoom;
use App\Models\GameRuleItem;
use App\Models\GameScoreTransaction;
use App\Models\GlobalUser;
use App\Services\Audit\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records and voids the results of a game room.
 *
 * A result is one winner against one or more losers of the same room. Points come from the rule item of the version
 * the room applies (never from the client) and are snapshotted on the result. The result, its losers, the score
 * transaction and the audit entry are written in one transaction, after re-checking that the room is still playing.
 * The idempotency key (unique per room) makes a retried submission return the first result instead of a duplicate.
 */
class GameResultService
{
    public function __construct(
        private readonly GameRoomService $rooms,
        private readonly AuditService $audit,
    ) {}

    /**
     * Record a result from the public link: the winner is always the player the visitor picked (never a value sent by
     * the client), so a visitor can only claim their own wins. Each submission is one result with one loser; several
     * results in the same round are allowed, each recorded by its own submission (own idempotency key).
     *
     * @param GameRoom $room Room (resolved from its public token).
     * @param GamePlayer $player Player picked by the visitor, who won.
     * @param int $ruleItemId Rule item of the version the room applies.
     * @param array<int, int> $loserIds Losing players.
     * @param string $idempotencyKey Client key of the submission (same key on retry).
     * @return array{result: GameResult, replayed: bool} The result, and whether it already existed for this key.
     *
     * @throws GameActionException When the room is not playing or the players / rule item are not valid.
     */
    public function recordFromPublicLink(GameRoom $room, GamePlayer $player, int $ruleItemId, array $loserIds, string $idempotencyKey): array
    {
        if ((int) $player->game_room_id !== (int) $room->id) {
            throw new GameActionException(__('games.errors.player_not_in_room'));
        }

        return $this->record($room, null, $ruleItemId, (int) $player->id, $loserIds, $idempotencyKey, (int) $player->id);
    }

    /**
     * Record a result in the latest round of a playing room.
     *
     * @param GameRoom $room Room.
     * @param GlobalUser|null $recorder Owner recording the result; null when recorded from the public link.
     * @param int $ruleItemId Rule item of the version the room applies.
     * @param int $winnerId Winning player.
     * @param array<int, int> $loserIds Losing players.
     * @param string $idempotencyKey Client key of the submission (same key on retry).
     * @param int|null $recordedByPlayerId Player who recorded it from the public link.
     * @return array{result: GameResult, replayed: bool} The result, and whether it already existed for this key.
     *
     * @throws GameActionException When the room is not playing or the players / rule item are not valid.
     */
    public function record(GameRoom $room, ?GlobalUser $recorder, int $ruleItemId, int $winnerId, array $loserIds, string $idempotencyKey, ?int $recordedByPlayerId = null): array
    {
        $existing = $this->findByKey($room, $idempotencyKey);
        if ($existing !== null) {
            return ['result' => $existing, 'replayed' => true];
        }

        try {
            return DB::transaction(function () use ($room, $recorder, $ruleItemId, $winnerId, $loserIds, $idempotencyKey, $recordedByPlayerId): array {
                $locked = $this->rooms->lock($room);
                $existing = $this->findByKey($locked, $idempotencyKey);
                if ($existing !== null) {
                    return ['result' => $existing, 'replayed' => true];
                }
                $this->rooms->assertPlaying($locked);

                $loserIds = array_values(array_unique(array_map('intval', $loserIds)));
                $this->assertPlayers($locked, $winnerId, $loserIds);
                $item = $this->ruleItem($locked, $ruleItemId);
                $round = $locked->rounds()->orderByDesc('number')->first();
                if ($round === null) {
                    throw new GameActionException(__('games.errors.not_playing'));
                }

                $result = GameResult::create([
                    'game_room_id' => $locked->id,
                    'game_round_id' => $round->id,
                    'winner_player_id' => $winnerId,
                    'game_rule_item_id' => $item->id,
                    'game_rule_version_id' => $item->game_rule_version_id,
                    'rule_item_name' => $item->name,
                    'points' => $item->points,
                    'rule_version_number' => (int) $item->version->version,
                    'idempotency_key' => $idempotencyKey,
                    'status' => GameResultStatus::Valid,
                    'recorded_by' => $recorder?->id,
                    'recorded_by_player_id' => $recordedByPlayerId,
                ]);
                $result->losers()->attach($loserIds);
                GameScoreTransaction::create([
                    'game_room_id' => $locked->id,
                    'game_result_id' => $result->id,
                    'game_player_id' => $winnerId,
                    'type' => GameScoreTransactionType::Award,
                    'points' => $item->points,
                ]);
                $locked->update(['last_activity_at' => now()]);
                $this->audit->record('game_result.recorded', 'game_result', (int) $result->id, null, [], [
                    'winner_player_id' => $winnerId,
                    'loser_player_ids' => $loserIds,
                    'rule_item' => $item->name,
                    'points' => $item->points,
                ], ['game_room_id' => $locked->id, 'round' => $round->number, 'recorded_by_player_id' => $recordedByPlayerId]);

                return ['result' => $result, 'replayed' => false];
            });
        } catch (UniqueConstraintViolationException) {
            // Two submissions with the same key raced past the first check: the other one won.
            $existing = $this->findByKey($room, $idempotencyKey);
            if ($existing === null) {
                throw new GameActionException(__('games.errors.retry'));
            }

            return ['result' => $existing, 'replayed' => true];
        }
    }

    /**
     * Void a result with a reason. The original score transaction is kept and a reversal cancels its points; the
     * result stays in the history but no longer counts in the statistics.
     *
     * @param GameResult $result Result.
     * @param GlobalUser $owner Owner voiding it.
     * @param string $reason Why the result is wrong.
     * @return void
     *
     * @throws GameActionException When the room is not playing or the result is already voided.
     */
    public function void(GameResult $result, GlobalUser $owner, string $reason): void
    {
        DB::transaction(function () use ($result, $owner, $reason): void {
            $room = $this->rooms->lock($result->gameRoom);
            $this->rooms->assertPlaying($room);
            $locked = GameResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoided()) {
                throw new GameActionException(__('games.errors.already_voided'));
            }

            $locked->update([
                'status' => GameResultStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $owner->id,
                'void_reason' => $reason,
            ]);
            $awards = GameScoreTransaction::query()
                ->where('game_result_id', $locked->id)
                ->where('type', GameScoreTransactionType::Award->value)
                ->get();
            foreach ($awards as $award) {
                GameScoreTransaction::create([
                    'game_room_id' => $award->game_room_id,
                    'game_result_id' => $award->game_result_id,
                    'game_player_id' => $award->game_player_id,
                    'type' => GameScoreTransactionType::Reversal,
                    'points' => -$award->points,
                    'reverses_transaction_id' => $award->id,
                ]);
            }
            $room->update(['last_activity_at' => now()]);
            $this->audit->record('game_result.voided', 'game_result', (int) $locked->id, null, ['status' => GameResultStatus::Valid->value], ['status' => GameResultStatus::Voided->value], ['game_room_id' => $room->id, 'reason' => $reason]);
            $result->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * @param GameRoom $room Room.
     * @param string $idempotencyKey Client key.
     * @return GameResult|null Result already recorded with this key.
     */
    private function findByKey(GameRoom $room, string $idempotencyKey): ?GameResult
    {
        return GameResult::query()->where('game_room_id', $room->id)->where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * Winner and losers must be distinct active players of the room, with at least one loser.
     *
     * @param GameRoom $room Room.
     * @param int $winnerId Winner.
     * @param array<int, int> $loserIds Losers (deduplicated).
     * @return void
     *
     * @throws GameActionException When a rule is broken.
     */
    private function assertPlayers(GameRoom $room, int $winnerId, array $loserIds): void
    {
        if ($loserIds === []) {
            throw new GameActionException(__('games.errors.loser_required'));
        }
        if (in_array($winnerId, $loserIds, true)) {
            throw new GameActionException(__('games.errors.winner_is_loser'));
        }
        $wanted = array_merge([$winnerId], $loserIds);
        $found = $room->players()->where('is_active', true)->whereIn('id', $wanted)->count();
        if ($found !== count($wanted)) {
            throw new GameActionException(__('games.errors.player_not_in_room'));
        }
    }

    /**
     * @param GameRoom $room Room.
     * @param int $ruleItemId Wanted rule item.
     * @return GameRuleItem Active item of the version the room applies.
     *
     * @throws GameActionException When the item is not part of that version or archived.
     */
    private function ruleItem(GameRoom $room, int $ruleItemId): GameRuleItem
    {
        $item = GameRuleItem::query()
            ->with('version')
            ->whereKey($ruleItemId)
            ->where('game_rule_version_id', $room->game_rule_version_id)
            ->where('is_active', true)
            ->first();
        if ($item === null) {
            throw new GameActionException(__('games.errors.rule_item_invalid'));
        }

        return $item;
    }
}
