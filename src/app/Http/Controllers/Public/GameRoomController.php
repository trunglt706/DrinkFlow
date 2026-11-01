<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\GameRoomStatus;
use App\Exceptions\GameActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\RecordPublicGameResultRequest;
use App\Services\Game\GamePublicAccessService;
use App\Services\Game\GameResultService;
use App\Services\Game\GameStatisticsService;
use App\Services\Realtime\SocketTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public page of a game room, reached through its capability token (no sign-in). The visitor picks which player
 * they are (a player held by another visitor cannot be picked); they may then record their own wins (category + losers). The room owner, when signed in, also gets the
 * "new round" action (posted to the owner route, which checks GameRoomPolicy).
 */
class GameRoomController extends Controller
{
    /** Query value clearing the chosen player. */
    private const CLEAR_PLAYER = 'none';

    public function __construct(private readonly GamePublicAccessService $access) {}

    /**
     * Show the public room, or a clear message when the token is unknown or revoked.
     *
     * @param Request $request Current request.
     * @param string $token Public token.
     * @param GameStatisticsService $statistics Statistics read model.
     * @return Response Page (404 when the link is invalid, 410 when the room is closed).
     */
    public function show(Request $request, string $token, GameStatisticsService $statistics): Response
    {
        $room = $this->access->findRoom($token);
        if ($room === null) {
            return response()->view('public.game-room-invalid', [], 404);
        }
        if ($this->access->isClosed($room)) {
            return response()->view('public.game-room-invalid', ['closed' => true], 410);
        }
        if ($room->status === GameRoomStatus::Waiting) {
            // Not started yet: only the basic room info and a reload action, no players picker or scoreboard.
            $room->load('game')->loadCount('players');

            return response()->view('public.game-room-waiting', [
                'room' => $room,
                'token' => $token,
                'isOwner' => $request->user('web')?->can('manage', $room) === true,
            ]);
        }

        $session = $request->session();
        $wanted = $request->query('player');
        $claimRefused = false;
        if ($wanted === self::CLEAR_PLAYER) {
            $this->access->clearPlayer($session, $room);
        } elseif (is_string($wanted) && ctype_digit($wanted)) {
            $claimRefused = ! $this->access->selectPlayer($session, $room, (int) $wanted);
        }
        $selected = $this->access->selectedPlayer($session, $room);
        if ($selected !== null) {
            $this->access->refreshClaim($session, $selected);
        }
        $room->load('ruleVersion.items');
        $stats = $statistics->dashboard($room);

        return response()->view('public.game-room', [
            'room' => $room,
            'token' => $token,
            'selected' => $selected,
            'selectedRow' => $selected === null ? null : collect($stats['leaderboard'])->firstWhere('player_id', (int) $selected->id),
            'clearPlayer' => self::CLEAR_PLAYER,
            'claimedIds' => $this->access->claimedByOthers($session, $room),
            'claimRefused' => $claimRefused,
            'ruleItems' => $room->ruleVersion->items->where('is_active', true)->values(),
            'canRecord' => $room->status === GameRoomStatus::Playing && $selected?->is_active === true,
            'isOwner' => $request->user('web')?->can('manage', $room) === true,
        ] + $stats);
    }

    /**
     * Realtime token of the room's public channel, so the page reloads when the owner starts or finishes the room.
     *
     * @param string $token Public token.
     * @param SocketTokenService $tokens Socket token issuer.
     * @return JsonResponse Token, 404 for an invalid link, 410 for a closed room.
     */
    public function socketToken(string $token, SocketTokenService $tokens): JsonResponse
    {
        $room = $this->access->findRoom($token);
        if ($room === null) {
            return response()->json(['message' => __('games.public.invalid_title')], 404);
        }
        if ($this->access->isClosed($room)) {
            return response()->json(['message' => __('games.public.closed_desc')], 410);
        }

        return response()->json(['data' => ['token' => $tokens->issueForGameViewer($room)]]);
    }

    /**
     * Record a win of the visitor's player. Retrying with the same idempotency key answers the first result.
     *
     * @param RecordPublicGameResultRequest $request Validated category, losers and key.
     * @param string $token Public token.
     * @param GameResultService $results Result service.
     * @return JsonResponse 201 when recorded, 200 for a replay, 404 for an invalid link, 410 for a closed room,
     *                      422 when refused.
     *
     * @throws GameActionException When no player is picked or a business rule refuses the result.
     */
    public function recordResult(RecordPublicGameResultRequest $request, string $token, GameResultService $results): JsonResponse
    {
        $room = $this->access->findRoom($token);
        if ($room === null) {
            return response()->json(['message' => __('games.public.invalid_title')], 404);
        }
        if ($this->access->isClosed($room)) {
            return response()->json(['message' => __('games.public.closed_desc')], 410);
        }
        $player = $this->access->selectedPlayer($request->session(), $room);
        $claim = $this->access->currentClaim($request->session());
        if ($player === null || $claim === null) {
            // No player held any more (claim expired / taken): the page reloads to show the picker again.
            throw GameActionException::stale(__('games.play.choose_first'));
        }

        $outcome = $results->recordFromPublicLink(
            $room,
            $player,
            (int) $request->validated('rule_item_id'),
            [(int) $request->validated('loser_id')],
            (string) $request->validated('idempotency_key'),
            $claim,
            $request->validated('round') === null ? null : (int) $request->validated('round'),
        );

        return response()->json([
            'message' => __('games.flash.result_recorded'),
            'result_id' => $outcome['result']->id,
            'replayed' => $outcome['replayed'],
        ], $outcome['replayed'] ? 200 : 201);
    }
}
