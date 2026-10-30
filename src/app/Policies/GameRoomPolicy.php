<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameRoom;
use App\Models\GlobalUser;

/**
 * Only the owner manages a game room (start, record results, players, public link). Viewers of the public link get
 * read-only access through the token, never through this policy.
 */
class GameRoomPolicy
{
    /**
     * Manage the room.
     *
     * @param GlobalUser $user Signed-in user.
     * @param GameRoom $gameRoom Game room.
     * @return bool True for the owner.
     */
    public function manage(GlobalUser $user, GameRoom $gameRoom): bool
    {
        return (int) $gameRoom->owner_id === (int) $user->id;
    }
}
