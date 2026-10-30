<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SaveGamePlayerRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Services\Game\GameRoomService;
use Illuminate\Http\RedirectResponse;

/**
 * Owner manages the players of a game room (add, rename, deactivate). Player ids never change.
 */
class GamePlayersController extends Controller
{
    public function __construct(private readonly GameRoomService $rooms) {}

    /**
     * Add a player.
     *
     * @param SaveGamePlayerRequest $request Validated form (owner checked).
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @return RedirectResponse Back to the dashboard.
     */
    public function store(SaveGamePlayerRequest $request, Game $game, GameRoom $gameRoom): RedirectResponse
    {
        $this->rooms->addPlayer($gameRoom, (string) $request->validated('display_name'));

        return back()->with('status', __('games.flash.player_added'));
    }

    /**
     * Rename or (de)activate a player.
     *
     * @param SaveGamePlayerRequest $request Validated form (owner checked).
     * @param Game $game Game.
     * @param GameRoom $gameRoom Room.
     * @param GamePlayer $player Player (scoped to the room).
     * @return RedirectResponse Back to the dashboard.
     */
    public function update(SaveGamePlayerRequest $request, Game $game, GameRoom $gameRoom, GamePlayer $player): RedirectResponse
    {
        $this->rooms->updatePlayer($player, (string) $request->validated('display_name'), (bool) $request->validated('is_active'));

        return back()->with('status', __('games.flash.player_updated'));
    }
}
