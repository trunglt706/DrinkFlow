<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\RecordGameResultRequest;
use App\Http\Requests\Game\VoidGameResultRequest;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameRoom;
use App\Services\Game\GameResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Owner records results (JSON, from the record sheet of the dashboard) and voids wrong ones.
 */
class GameResultsController extends Controller
{
    public function __construct(private readonly GameResultService $results) {}

    /**
     * Record a result. A retry with the same idempotency key answers the first result (200) instead of a new one (201).
     *
     * @param RecordGameResultRequest $request Validated payload (owner checked).
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return JsonResponse Result id and confirmation message.
     */
    public function store(RecordGameResultRequest $request, Game $game, GameRoom $gameRoom): JsonResponse
    {
        $outcome = $this->results->record(
            $gameRoom,
            $this->currentGlobalUser($request),
            (int) $request->validated('rule_item_id'),
            (int) $request->validated('winner_id'),
            array_map('intval', (array) $request->validated('loser_ids')),
            (string) $request->validated('idempotency_key'),
            expectedRound: $request->validated('round') === null ? null : (int) $request->validated('round'),
        );

        return response()->json([
            'message' => __('games.flash.result_recorded'),
            'result_id' => $outcome['result']->id,
            'replayed' => $outcome['replayed'],
        ], $outcome['replayed'] ? 200 : 201);
    }

    /**
     * Void a result with a reason.
     *
     * @param VoidGameResultRequest $request Validated reason (owner checked).
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @param GameResult $result Result (scoped to the room).
     * @return RedirectResponse Back to the dashboard.
     */
    public function void(VoidGameResultRequest $request, Game $game, GameRoom $gameRoom, GameResult $result): RedirectResponse
    {
        $this->results->void($result, $this->currentGlobalUser($request), (string) $request->validated('reason'));

        return back()->with('status', __('games.flash.result_voided'));
    }
}
