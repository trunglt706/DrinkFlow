<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Constants\AppLocale;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Admin;
use App\Models\Debt;
use App\Models\Room;
use App\Models\Order;
use App\Models\Scopes\CampaignDebtScope;
use App\Services\Notification\AdminNotificationService;
use App\Services\Notification\NotificationPresentationService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminLayoutComposer
{
    /** Session key of the room the Agent operated last (written by EnsureAdminRoomAccess). */
    public const CONTEXT_ROOM_SESSION_KEY = 'admin_context_room_id';

    /**
     * Create a composer for shared admin layout data.
     *
     * @param AdminNotificationService $notifications Service for unread admin notifications.
     * @param NotificationPresentationService $presentation Service for localized notification text.
     */
    public function __construct(
        private readonly AdminNotificationService $notifications,
        private readonly NotificationPresentationService $presentation,
    ) {
    }

    /**
     * Share the active room, live campaign and notification data with admin layouts.
     *
     * @param View $view Admin layout view instance.
     * @return void
     */
    public function compose(View $view): void
    {
        $data = $view->getData();
        $room = $data['room'] ?? request()->attributes->get('room') ?? request()->route('room');
        $adminUser = auth('admin')->user();
        $adminUser = $adminUser instanceof Admin ? $adminUser : null;
        $assignedRooms = $data['assignedRooms'] ?? null;
        $assignedRoomsList = $this->assignedRooms($adminUser, $assignedRooms, $room);
        if (! $room instanceof Room) {
            // Pages outside a room use the same sidebar, header and bell as the room pages.
            $room = $this->contextRoom($assignedRoomsList);
        }
        $unreadNotifications = $room instanceof Room && $adminUser instanceof Admin
            ? $this->notifications->unreadForRoom($adminUser, $room)
            : collect();

        $view->with([
            'room' => $room,
            'adminUser' => $adminUser,
            'assignedRoomsList' => $assignedRoomsList,
            'hasLiveCampaign' => $room instanceof Room && $room->campaigns()
                ->where('status', CampaignStatus::Active->value)
                ->exists(),
            'realtimeOrderCount' => $room instanceof Room
                ? Order::query()->where('room_id', $room->id)->inLiveCampaign()
                    ->where('status', '!=', OrderStatus::Cancelled->value)
                    ->whereNull('cancelled_at')
                    ->count()
                : 0,
            'birthdayMonthCount' => $room instanceof Room
                ? app(\App\Services\Birthday\RoomBirthdayService::class)->monthCount($room)
                : 0,
            'blockedUsersCount' => $room instanceof Room
                ? $room->roomUsers()->where('status', RoomUserStatus::Blocked->value)->count()
                : 0,
            // Payments waiting for review: single-debt confirmations plus consolidated requests (their
            // bundled children keep their own status, so nothing is counted twice).
            'pendingPaymentRequestsCount' => $room instanceof Room
                ? Debt::query()->withoutGlobalScope(CampaignDebtScope::class)
                    ->where('room_id', $room->id)
                    ->where('status', DebtStatus::Pending->value)
                    ->count()
                : 0,
            'unreadNotifications' => $unreadNotifications,
            'unreadCount' => $unreadNotifications->count(),
            'notificationPresentations' => $this->presentNotifications($unreadNotifications),
            'currentLocale' => app()->getLocale(),
            'locales' => AppLocale::SUPPORTED,
            'activeLocaleMeta' => AppLocale::get(app()->getLocale()),
        ]);
    }

    /**
     * Resolve rooms available in the admin workspace selector.
     *
     * @param ?Admin $admin Current admin account.
     * @param mixed $assignedRooms Rooms explicitly supplied by the caller.
     * @param mixed $room Active room.
     * @return Collection<int, Room> Available rooms.
     */
    private function assignedRooms(?Admin $admin, mixed $assignedRooms, mixed $room): Collection
    {
        $rooms = $assignedRooms instanceof Collection
            ? $assignedRooms
            : ($admin?->rooms()->where('status', RoomStatus::Active)->orderBy('name')->get() ?? collect());

        if ($room instanceof Room && !$rooms->contains('id', $room->id)) {
            $rooms->prepend($room);
        }

        return $rooms;
    }

    /**
     * Room whose menu a roomless admin page (my rooms, subscription, billing, profile) shows.
     *
     * Only rooms from the Agent's own operable list qualify, so a stale or foreign ID kept in the
     * session never exposes another room; without a remembered room the first one is used.
     *
     * @param Collection<int, Room> $rooms Rooms the signed-in Agent can operate.
     * @return Room|null Context room, or null when the Agent has no active room yet.
     */
    private function contextRoom(Collection $rooms): ?Room
    {
        $rememberedId = (int) session(self::CONTEXT_ROOM_SESSION_KEY, 0);

        return $rooms->firstWhere('id', $rememberedId) ?? $rooms->first();
    }

    /**
     * Build presentation data for each unread notification.
     *
     * @param Collection<int, mixed> $notifications Unread notifications.
     * @return array<int|string, array{title: string, body: string, icon: string, link: ?string}> Presentation keyed by notification id.
     */
    private function presentNotifications(Collection $notifications): array
    {
        $presentations = [];
        foreach ($notifications as $notification) {
            $presentations[$notification->getKey()] = $this->presentation->present($notification);
        }

        return $presentations;
    }
}
