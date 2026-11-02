<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameNotificationChannel;
use App\Models\GlobalUser;

class GameNotificationChannelPolicy
{
    /**
     * Use, edit or delete the channel.
     *
     * @param GlobalUser $user Signed-in user.
     * @param GameNotificationChannel $channel Channel.
     * @return bool True for the owner.
     */
    public function manage(GlobalUser $user, GameNotificationChannel $channel): bool
    {
        return (int) $channel->owner_id === (int) $user->id;
    }
}
