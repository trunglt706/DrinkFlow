<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\Game\GameRoomService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Trò chơi" catalogue of the /me portal and, per game, the history of the rooms the user created.
 */
class GamesController extends Controller
{
    /**
     * List the games (/me/games).
     *
     * @param Request $request Current request.
     * @return View Games page.
     */
    public function index(Request $request): View
    {
        return view('user.global.games.index', [
            'user' => $this->currentGlobalUser($request),
            'games' => Game::query()->listed()->get(),
            'breadcrumbs' => [
                ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
                ['label' => __('games.page_title'), 'url' => route('user.me.games')],
            ],
        ]);
    }

    /**
     * Rooms the user created for a game (/me/games/{game}), filterable by active / finished.
     *
     * @param Request $request Current request.
     * @param Game $game Game (by slug).
     * @param GameRoomService $rooms Room service.
     * @return View Room history page.
     */
    public function show(Request $request, Game $game, GameRoomService $rooms): View
    {
        abort_unless($game->is_active, 404);
        $user = $this->currentGlobalUser($request);
        $filter = in_array($request->query('status'), [GameRoomService::FILTER_ACTIVE, GameRoomService::FILTER_FINISHED], true)
            ? (string) $request->query('status')
            : null;

        return view('user.global.games.show', [
            'user' => $user,
            'game' => $game,
            'filter' => $filter,
            'rooms' => $rooms->listForOwner($game, $user, $filter),
            'breadcrumbs' => [
                ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
                ['label' => __('games.page_title'), 'url' => route('user.me.games')],
                ['label' => $game->name, 'url' => route('user.me.games.show', $game)],
            ],
        ]);
    }
}
