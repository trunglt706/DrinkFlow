<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameRoomStatus;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Contracts\Session\Session;

/**
 * Access through the public link of a game room: the token resolves the room, and the visitor's chosen player is
 * kept in their session (per room). That player is who the visitor records wins for on the public page.
 */
class GamePublicAccessService
{
    /**
     * Room of a public token.
     *
     * @param string $token Public token from the URL.
     * @return GameRoom|null Room, or null when the token is unknown or was revoked.
     */
    public function findRoom(string $token): ?GameRoom
    {
        return GameRoom::query()->with('game')->where('public_token', $token)->first();
    }

    /**
     * A closed room is no longer reachable through its public link: it is finished, or its game was turned off by a
     * Superadmin. The owner keeps the full history in /me.
     *
     * @param GameRoom $room Room.
     * @return bool True when the public link must refuse access.
     */
    public function isClosed(GameRoom $room): bool
    {
        return $room->status === GameRoomStatus::Finished || $room->game?->is_active !== true;
    }

    /**
     * Player the visitor picked for this room.
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @return GamePlayer|null Player of the room, or null when none is picked (or it no longer exists).
     */
    public function selectedPlayer(Session $session, GameRoom $room): ?GamePlayer
    {
        $playerId = $session->get($this->sessionKey($room));
        if (! is_int($playerId)) {
            return null;
        }

        return $room->players()->whereKey($playerId)->first();
    }

    /**
     * Remember the visitor's player for this room.
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @param int $playerId Wanted player.
     * @return bool True when the player belongs to the room and was remembered.
     */
    public function selectPlayer(Session $session, GameRoom $room, int $playerId): bool
    {
        if (! $room->players()->whereKey($playerId)->exists()) {
            return false;
        }
        $session->put($this->sessionKey($room), $playerId);

        return true;
    }

    /**
     * Forget the visitor's player for this room.
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @return void
     */
    public function clearPlayer(Session $session, GameRoom $room): void
    {
        $session->forget($this->sessionKey($room));
    }

    /**
     * @param GameRoom $room Room.
     * @return string Session key of the chosen player.
     */
    private function sessionKey(GameRoom $room): string
    {
        return 'game_rooms.'.$room->id.'.player';
    }
}
