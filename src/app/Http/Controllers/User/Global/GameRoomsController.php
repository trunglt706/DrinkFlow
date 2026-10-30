<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreGameRoomRequest;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\GameRuleSet;
use App\Models\GlobalUser;
use App\Services\Game\GameRoomService;
use App\Services\Game\GameRuleSetService;
use App\Services\Game\GameStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Owner side of a game room: create it, its dashboard, and the lifecycle / public link actions.
 */
class GameRoomsController extends Controller
{
    public function __construct(private readonly GameRoomService $rooms) {}

    /**
     * Create-room form: name, players and rule set on one screen.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRuleSetService $ruleSets Rule set service.
     * @return View Form page.
     */
    public function create(Request $request, Game $game, GameRuleSetService $ruleSets): View
    {
        abort_unless($game->is_active, 404);
        $user = $this->currentGlobalUser($request);

        return view('user.global.games.create', [
            'user' => $user,
            'game' => $game,
            'ruleSets' => $ruleSets->forOwner($game, $user),
            'defaultPlayers' => GameRoomService::DEFAULT_PLAYERS,
            'minPlayers' => GameRoomService::MIN_PLAYERS,
            'maxPlayers' => GameRoomService::MAX_PLAYERS,
            'breadcrumbs' => $this->breadcrumbs($game, ['label' => __('games.create.title'), 'url' => route('user.me.games.rooms.create', $game)]),
        ]);
    }

    /**
     * Create the room and open its dashboard.
     *
     * @param StoreGameRoomRequest $request Validated form.
     * @param Game $game Game.
     * @return RedirectResponse Dashboard of the new room.
     */
    public function store(StoreGameRoomRequest $request, Game $game): RedirectResponse
    {
        abort_unless($game->is_active, 404);
        $user = $this->currentGlobalUser($request);
        $ruleSet = GameRuleSet::query()->whereKey((int) $request->validated('rule_set_id'))->where('game_id', $game->id)->first();
        if ($ruleSet === null || ! $user->can('manage', $ruleSet)) {
            return back()->withInput()->withErrors(['rule_set_id' => __('games.errors.rule_set_invalid')]);
        }

        $room = $this->rooms->create($game, $user, (string) $request->validated('name'), (array) $request->validated('players'), $ruleSet);

        return redirect()->route('user.me.games.rooms.show', [$game, $room])->with('status', __('games.flash.created'));
    }

    /**
     * Room dashboard: summary, leaderboard, head-to-head, history and the owner's actions.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room (scoped to the game).
     * @param GameStatisticsService $statistics Statistics read model.
     * @return View Dashboard page.
     */
    public function show(Request $request, Game $game, GameRoom $gameRoom, GameStatisticsService $statistics): View
    {
        $user = $this->authorizeOwner($request, $gameRoom);
        $gameRoom->load(['ruleSet', 'ruleVersion.items']);

        return view('user.global.games.room', [
            'user' => $user,
            'game' => $game,
            'room' => $gameRoom,
            'ruleItems' => $gameRoom->ruleVersion->items->where('is_active', true)->values(),
            'hasNewerRuleVersion' => $gameRoom->ruleSet->current_version_id !== null
                && (int) $gameRoom->ruleSet->current_version_id !== (int) $gameRoom->game_rule_version_id,
            'latestRuleVersion' => $gameRoom->ruleSet->currentVersion?->version,
            'breadcrumbs' => $this->breadcrumbs($game, ['label' => $gameRoom->name, 'url' => route('user.me.games.rooms.show', [$game, $gameRoom])]),
        ] + $statistics->dashboard($gameRoom));
    }

    /**
     * Start a waiting room (opens round 1).
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function start(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $this->rooms->start($gameRoom);

        return back()->with('status', __('games.flash.started'));
    }

    /**
     * Open the next round.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function openRound(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $round = $this->rooms->openRound($gameRoom);

        return back()->with('status', __('games.flash.round_opened', ['number' => $round->number]));
    }

    /**
     * Finish the room.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function finish(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $this->rooms->finish($gameRoom);

        return back()->with('status', __('games.flash.finished'));
    }

    /**
     * Apply the latest version of the room's rule set.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function applyRuleVersion(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $this->rooms->applyLatestRuleVersion($gameRoom);

        return back()->with('status', __('games.flash.rule_version_applied'));
    }

    /**
     * Issue a new public link (the old one stops working).
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function regenerateLink(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $this->rooms->regeneratePublicToken($gameRoom);

        return back()->with('status', __('games.flash.link_regenerated'));
    }

    /**
     * Revoke the public link.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function revokeLink(Request $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->authorizeOwner($request, $gameRoom);
        $this->rooms->revokePublicToken($gameRoom);

        return back()->with('status', __('games.flash.link_revoked'));
    }

    /**
     * Only the owner manages a room.
     *
     * @param Request $request Current request.
     * @param GameRoom $gameRoom Room.
     * @return GlobalUser Signed-in owner.
     */
    private function authorizeOwner(Request $request, GameRoom $gameRoom): GlobalUser
    {
        $user = $this->currentGlobalUser($request);
        Gate::forUser($user)->authorize('manage', $gameRoom);

        return $user;
    }

    /**
     * Breadcrumbs: personal space › games › game › last.
     *
     * @param Game $game Game.
     * @param array{label: string, url: string} $last Last crumb.
     * @return array<int, array{label: string, url: string}> Breadcrumbs.
     */
    private function breadcrumbs(Game $game, array $last): array
    {
        return [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('games.page_title'), 'url' => route('user.me.games')],
            ['label' => $game->name, 'url' => route('user.me.games.show', $game)],
            $last,
        ];
    }
}
