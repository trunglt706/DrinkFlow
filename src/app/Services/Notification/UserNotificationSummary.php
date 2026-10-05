<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\GlobalUser;
use Illuminate\Support\Collection;

/**
 * Load the global user's unread notification summary once per request.
 */
class UserNotificationSummary
{
    /** @var array<int, array{notifications: Collection, unread_count: int}> */
    private array $cache = [];

    /**
     * @return array{notifications: Collection, unread_count: int}
     */
    public function for(GlobalUser $user): array
    {
        return $this->cache[$user->id] ??= [
            'notifications' => $user->notifications()
                ->whereNull('read_at')
                ->latest('created_at')
                ->take(5)
                ->get(),
            'unread_count' => $user->notifications()
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
