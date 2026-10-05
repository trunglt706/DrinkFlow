<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Superadmin;
use App\Services\Notification\NotificationPresentationService;
use App\Services\Notification\SuperadminNotificationService;
use App\Support\Superadmin\SuperadminNavigation;
use Illuminate\View\View;

/**
 * Shares the menu (sidebar, breadcrumb, command palette) and the header bell data (the signed-in account's unread
 * notifications) with the superadmin layout.
 */
class SuperadminLayoutComposer
{
    /** Number of unread notifications previewed in the header dropdown. */
    private const DROPDOWN_LIMIT = 5;

    /**
     * @param SuperadminNotificationService $notifications Superadmin notification service.
     * @param NotificationPresentationService $presentation Localized title/body/icon for each notification.
     * @param SuperadminNavigation $navigation Permission-filtered console menu.
     */
    public function __construct(
        private readonly SuperadminNotificationService $notifications,
        private readonly NotificationPresentationService $presentation,
        private readonly SuperadminNavigation $navigation,
    ) {
    }

    /**
     * Share the menu, unread notifications, their total count and their display data with the layout.
     *
     * @param View $view Superadmin layout view.
     * @return void
     */
    public function compose(View $view): void
    {
        $admin = auth('superadmin')->user();
        $navSections = $this->navigation->sectionsFor($admin instanceof Superadmin ? $admin : null);
        $active = $view->getData()['active'] ?? null;
        $view->with([
            'navSections' => $navSections,
            'navCurrent' => $this->navigation->locate($navSections, is_string($active) ? $active : null),
        ]);

        if (!$admin instanceof Superadmin) {
            $view->with(['headerNotifications' => collect(), 'headerNotificationPresentations' => [], 'headerUnreadCount' => 0]);

            return;
        }

        $unread = $this->notifications->unreadFor($admin, self::DROPDOWN_LIMIT);
        $presentations = [];
        foreach ($unread as $notification) {
            $presentations[$notification->id] = $this->presentation->present($notification);
        }

        $view->with([
            'headerNotifications' => $unread,
            'headerNotificationPresentations' => $presentations,
            'headerUnreadCount' => $this->notifications->unreadCountFor($admin),
        ]);
    }
}
