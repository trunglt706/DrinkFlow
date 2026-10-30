<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameResultStatus;
use App\Models\GamePlayer;
use App\Models\GameResult;
use App\Models\GameRoom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model of a game room shared by the owner dashboard and the public page: summary, leaderboard, head-to-head
 * and history.
 *
 * - Points: sum of the player's score transactions (a void adds a reversal, so voided results cancel out).
 * - Wins: valid results the player won. Losses: valid results listing the player as loser (a result with several
 *   losers is one win for the winner and one loss for each loser).
 * - Ranking: points DESC, wins DESC, display name ASC.
 */
class GameStatisticsService
{
    /** Number of results shown in the history. */
    public const HISTORY_LIMIT = 100;

    /**
     * Everything the room dashboard shows.
     *
     * @param GameRoom $room Room.
     * @return array{
     *     summary: array{rounds: int, results: int, current_round: int|null},
     *     leaderboard: array<int, array{rank: int, player_id: int, name: string, is_active: bool, points: int, wins: int, losses: int}>,
     *     headToHead: array<int, array<int, array{opponent: string, points: int|float, wins: int, losses: int}>>,
     *     history: Collection<int, GameResult>,
     *     players: Collection<int, GamePlayer>
     * }
     */
    public function dashboard(GameRoom $room): array
    {
        $players = $room->players()->get();
        $validResults = GameResult::query()->where('game_room_id', $room->id)->where('status', GameResultStatus::Valid->value);

        $points = DB::table('game_score_transactions')
            ->where('game_room_id', $room->id)
            ->groupBy('game_player_id')
            ->selectRaw('game_player_id, SUM(points) as total')
            ->pluck('total', 'game_player_id');
        $wins = (clone $validResults)
            ->groupBy('winner_player_id')
            ->selectRaw('winner_player_id, COUNT(*) as total')
            ->pluck('total', 'winner_player_id');
        // One row per (valid result, loser): the snapshot points of the result and who lost it.
        $pairs = DB::table('game_result_losers')
            ->join('game_results', 'game_results.id', '=', 'game_result_losers.game_result_id')
            ->where('game_results.game_room_id', $room->id)
            ->where('game_results.status', GameResultStatus::Valid->value)
            ->select(['game_results.id as result_id', 'game_results.winner_player_id as winner_id', 'game_results.points', 'game_result_losers.game_player_id as loser_id'])
            ->get();
        $losersPerResult = $pairs->countBy('result_id');

        // $beat[winner][loser] = valid results where winner beat loser; $earned[winner][loser] = points the winner
        // earned against that loser (a result with several losers splits its points evenly, so the per-opponent
        // points add up to the player's total).
        $beat = [];
        $earned = [];
        $losses = [];
        foreach ($pairs as $pair) {
            $winnerId = (int) $pair->winner_id;
            $loserId = (int) $pair->loser_id;
            $beat[$winnerId][$loserId] = ($beat[$winnerId][$loserId] ?? 0) + 1;
            $earned[$winnerId][$loserId] = ($earned[$winnerId][$loserId] ?? 0) + (int) $pair->points / $losersPerResult[$pair->result_id];
            $losses[$loserId] = ($losses[$loserId] ?? 0) + 1;
        }

        $rows = $players->map(static fn (GamePlayer $player): array => [
            'rank' => 0,
            'player_id' => (int) $player->id,
            'name' => $player->display_name,
            'is_active' => $player->is_active,
            'points' => (int) ($points[$player->id] ?? 0),
            'wins' => (int) ($wins[$player->id] ?? 0),
            'losses' => (int) ($losses[$player->id] ?? 0),
        ])->all();
        usort($rows, static fn (array $a, array $b): int => [$b['points'], $b['wins'], mb_strtolower($a['name'])] <=> [$a['points'], $a['wins'], mb_strtolower($b['name'])]);
        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        $headToHead = [];
        foreach ($players as $player) {
            $headToHead[(int) $player->id] = [];
            foreach ($players as $opponent) {
                if ($opponent->id === $player->id) {
                    continue;
                }
                $won = $beat[(int) $player->id][(int) $opponent->id] ?? 0;
                $lost = $beat[(int) $opponent->id][(int) $player->id] ?? 0;
                if ($won > 0 || $lost > 0) {
                    $headToHead[(int) $player->id][] = [
                        'opponent' => $opponent->display_name,
                        'points' => $this->roundPoints($earned[(int) $player->id][(int) $opponent->id] ?? 0),
                        'wins' => $won,
                        'losses' => $lost,
                    ];
                }
            }
        }

        $currentRound = $room->rounds()->max('number');

        return [
            'summary' => [
                'rounds' => $room->rounds()->count(),
                'results' => (clone $validResults)->count(),
                'current_round' => $currentRound === null ? null : (int) $currentRound,
            ],
            'leaderboard' => $rows,
            'headToHead' => $headToHead,
            'history' => $room->results()
                ->with(['winner', 'losers', 'round', 'recordedByPlayer'])
                ->latest('id')
                ->limit(self::HISTORY_LIMIT)
                ->get(),
            'players' => $players,
        ];
    }

    /**
     * Points shown per opponent: whole numbers stay integers, an even split keeps two decimals at most.
     *
     * @param int|float $points Raw sum.
     * @return int|float Display value.
     */
    private function roundPoints(int|float $points): int|float
    {
        $rounded = round($points, 2);

        return floor($rounded) === $rounded ? (int) $rounded : $rounded;
    }
}
