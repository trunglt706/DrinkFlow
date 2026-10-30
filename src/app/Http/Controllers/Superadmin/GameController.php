<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SaveGameRequest;
use App\Models\Game;
use App\Services\Game\GameCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Games of the /me "Trò chơi" catalogue (Content → Games).
 */
class GameController extends Controller
{
    public function __construct(private readonly GameCatalogService $catalog) {}

    /**
     * List the games with their usage.
     *
     * @return View Game list.
     */
    public function index(): View
    {
        return view('superadmin.games.index', ['games' => $this->catalog->list()]);
    }

    /**
     * Edit page of one game.
     *
     * @param Game $game Game.
     * @return View Detail page.
     */
    public function show(Game $game): View
    {
        return view('superadmin.games.show', [
            'game' => $game->loadCount(['gameRooms as rooms_count', 'ruleSets as rule_sets_count']),
            'inUse' => $this->catalog->isInUse($game),
        ]);
    }

    /**
     * Create a game.
     *
     * @param SaveGameRequest $request Validated game data.
     * @return RedirectResponse Game list.
     */
    public function store(SaveGameRequest $request): RedirectResponse
    {
        $this->catalog->create($request->validated());

        return redirect()->route('superadmin.games.index')->with('status', __('games.admin.created'));
    }

    /**
     * Update a game.
     *
     * @param SaveGameRequest $request Validated game data.
     * @param Game $game Game.
     * @return RedirectResponse Detail page.
     */
    public function update(SaveGameRequest $request, Game $game): RedirectResponse
    {
        $this->catalog->update($game, $request->validated());

        return redirect()->route('superadmin.games.show', $game)->with('status', __('games.admin.updated'));
    }

    /**
     * Delete an unused game.
     *
     * @param Game $game Game.
     * @return RedirectResponse Game list.
     */
    public function destroy(Game $game): RedirectResponse
    {
        $this->catalog->delete($game);

        return redirect()->route('superadmin.games.index')->with('status', __('games.admin.deleted'));
    }
}
