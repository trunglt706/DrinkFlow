<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Constants\AppLocale;
use App\Models\GlobalUser;
use App\Services\Notification\UserNotificationSummary;
use Illuminate\View\View;

class UserGlobalLayoutComposer
{
    public function __construct(private readonly UserNotificationSummary $notificationSummary) {}

    /**
     * Share user profile and unread notification data with the global user portal layout.
     *
     * @param  View  $view  Blade view instance.
     */
    public function compose(View $view): void
    {
        $data = $view->getData();
        $user = $data['user'] ?? request()->attributes->get('global_user') ?? auth('web')->user();
        $notifications = $data['notifications'] ?? null;
        $unreadCount = $data['unreadNotificationsCount'] ?? null;

        if ($user instanceof GlobalUser) {
            $summary = $this->notificationSummary->for($user);
            $notifications ??= $summary['notifications'];
            $unreadCount ??= $summary['unread_count'];
        } else {
            $notifications = $notifications ?? collect();
            $unreadCount = $unreadCount ?? 0;
        }

        $currentLocale = app()->getLocale();

        $view->with([
            'user' => $user,
            'notifications' => $notifications,
            'unreadNotificationsCount' => (int) ($unreadCount ?? 0),
            'currentLocale' => $currentLocale,
            'locales' => AppLocale::SUPPORTED,
            'activeLocaleMeta' => AppLocale::get($currentLocale),
        ]);
    }
}
