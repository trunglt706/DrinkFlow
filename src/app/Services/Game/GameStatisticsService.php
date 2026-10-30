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
     *     headToHead: array<int, array<int, array{opponent: string, wins: int, losses: int}>>,
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
        $pairs = DB::table('game_result_losers')
            ->join('game_results', 'game_results.id', '=', 'game_result_losers.game_result_id')
            ->where('game_results.game_room_id', $room->id)
            ->where('game_results.status', GameResultStatus::Valid->value)
            ->groupBy('game_results.winner_player_id', 'game_result_losers.game_player_id')
            ->selectRaw('game_results.winner_player_id as winner_id, game_result_losers.game_player_id as loser_id, COUNT(*) as total')
            ->get();

        // $beat[winner][loser] = number of valid results where winner beat loser.
        $beat = [];
        $losses = [];
        foreach ($pairs as $pair) {
            $beat[(int) $pair->winner_id][(int) $pair->loser_id] = (int) $pair->total;
            $losses[(int) $pair->loser_id] = ($losses[(int) $pair->loser_id] ?? 0) + (int) $pair->total;
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
                    $headToHead[(int) $player->id][] = ['opponent' => $opponent->display_name, 'wins' => $won, 'losses' => $lost];
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
}
