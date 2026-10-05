<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Room\UserRoomsService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserGlobalDashboardService
{
    public function __construct(private readonly UserRoomsService $roomService) {}

    /**
     * Tổng hợp toàn bộ dữ liệu thống kê, phòng gần đây, đơn hàng gần đây và thông báo cho Dashboard người dùng toàn hệ thống.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống đang đăng nhập
     * @return array<string, mixed> Mảng dữ liệu cho View Dashboard
     */
    public function getDashboardData(GlobalUser $user): array
    {
        // Extract Workspace domain from user email
        $domain = Str::after((string) $user->email, '@');
        $workspaceText = __('global.dashboard.workspace_auth_text', ['domain' => $domain ?: 'company.com']);

        // Query active room users of current user
        $roomUsers = $user->roomUsers()
            ->withCount('orders')
            ->withSum('orders', 'final_amount')
            ->withMax('orders', 'created_at')
            ->with(['room', 'room.campaigns' => function (HasMany $query): void {
                $query->where('status', CampaignStatus::Active)->orderByDesc('started_at')->orderByDesc('id');
            }])
            ->where('status', RoomUserStatus::Active->value)
            ->orderByDesc('last_active_at')
            ->orderByDesc('updated_at')
            ->get();

        $roomsCount = $roomUsers->count();
        $roomUserIds = $roomUsers->pluck('id');

        // Order metrics across all user memberships
        $ordersQuery = Order::query()->whereIn('room_user_id', $roomUserIds);
        $totalOrdersCount = (clone $ordersQuery)->count();

        $completedStatuses = [
            OrderStatus::Submitted->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Completed->value,
            PaymentStatus::Paid->value,
        ];

        $totalSpent = (int) (clone $ordersQuery)->whereIn('status', $completedStatuses)->sum('final_amount');
        $sponsorReceived = (int) (clone $ordersQuery)->whereIn('status', $completedStatuses)->sum('sponsor_amount');
        $savingsPercent = ($totalSpent + $sponsorReceived) > 0
            ? (int) round(($sponsorReceived / ($totalSpent + $sponsorReceived)) * 100)
            : 0;

        // Recent rooms (up to 4)
        $recentRooms = $roomUsers->take(4)->map(
            function (RoomUser $membership): object {
                $card = $this->roomService->formatRoomCard($membership);
                $campaign = $membership->room?->status === RoomStatus::Active
                    ? $membership->room->campaigns->first()
                    : null;
                $card->is_live = $campaign !== null;
                $card->live_deadline = $campaign?->deadline
                    ? FormatHelper::formatDateTime($campaign->deadline, 'd/m/Y H:i')
                    : null;
                $card->live_ordering_expired = $campaign?->deadline?->isPast() ?? false;

                return $card;
            }
        );

        // Recent orders (up to 5)
        $recentOrders = (clone $ordersQuery)
            ->where('status', OrderStatus::Completed->value)
            ->with(['room', 'campaign', 'items'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                $statusVal = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
                $isPaid = in_array($statusVal, [PaymentStatus::Paid->value, OrderStatus::Completed->value], true);
                $isPending = in_array($statusVal, [OrderStatus::Submitted->value, OrderStatus::Confirmed->value], true);

                $itemsText = $order->items->map(function ($item) {
                    $txt = $item->item_name;
                    if ($item->size_name) {
                        $txt .= ' ('.$item->size_name.')';
                    }
                    $txt .= ' x'.$item->quantity;
                    if ($item->note) {
                        $txt .= ', '.$item->note;
                    }

                    return $txt;
                })->join('; ');

                if (empty($itemsText)) {
                    $itemsText = __('global.dashboard.default_item_text');
                }

                $timeText = FormatHelper::formatDateTime($order->created_at, 'd/m, H:i');
                if ($order->created_at->isToday()) {
                    $timeText = __('global.dashboard.today_at', ['time' => FormatHelper::formatDateTime($order->created_at, 'H:i')]);
                } elseif ($order->created_at->isYesterday()) {
                    $timeText = __('global.dashboard.yesterday_at', ['time' => FormatHelper::formatDateTime($order->created_at, 'H:i')]);
                }

                return [
                    'id' => $order->id,
                    'code' => '#'.$order->code,
                    'time_formatted' => $timeText,
                    'room_name' => $order->room?->name ?? 'DrinkFlow Room',
                    'restaurant' => $order->campaign?->restaurant ?: 'Cửa hàng',
                    'items_summary' => $itemsText,
                    'final_amount' => $order->final_amount,
                    'final_amount_formatted' => FormatHelper::formatCurrency((int) $order->final_amount),
                    'is_paid' => $isPaid,
                    'is_pending' => $isPending,
                    'status_label' => $isPaid ? __('global.dashboard.paid') : ($isPending ? __('global.dashboard.unpaid') : __('global.common.cancelled')),
                    // The order page is addressed by the order code, and the room by its slug.
                    'detail_url' => $order->room instanceof Room
                        ? route('user.orders.page', ['room' => $order->room, 'order' => $order->code])
                        : '#',
                ];
            });

        return [
            'user' => $user,
            'workspaceText' => $workspaceText,
            'roomsCount' => $roomsCount,
            'totalOrdersCount' => $totalOrdersCount,
            'totalSpent' => $totalSpent,
            'sponsorReceived' => $sponsorReceived,
            'savingsPercent' => $savingsPercent,
            'recentRooms' => $recentRooms,
            'recentOrders' => $recentOrders,
        ];
    }
}
