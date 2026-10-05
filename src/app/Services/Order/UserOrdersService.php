<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Support\Traits\HandlesDatabaseDriver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class UserOrdersService
{
    use HandlesDatabaseDriver;

    /**
     * Thu thập danh sách đơn hàng phân trang và tính toán các chỉ số chi tiêu, tài trợ, nợ chờ thanh toán theo bộ lọc.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  Request  $request  Đối tượng HTTP Request chứa bộ lọc room, status, time_range, search
     * @return array<string, mixed> Mảng dữ liệu cho View lịch sử đơn hàng
     */
    public function getOrdersPageData(GlobalUser $user, Request $request): array
    {
        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.orders.breadcrumb_history'), 'url' => route('user.me.orders')],
        ];

        // Fetch user rooms for filter dropdown
        $userRooms = $user->roomUsers()->with('room')->get()->map->room->filter()->unique('id')->values();
        $roomUserIds = $user->roomUsers()->pluck('id');

        // Base query for user orders
        $query = Order::query()
            ->whereIn('room_user_id', $roomUserIds)
            ->with(['room', 'campaign.paymentAccount', 'roomUser.globalUser', 'parent.roomUser.globalUser', 'children.roomUser.globalUser', 'children.items.toppings', 'items.toppings'])
            ->latest();

        // Filter: Room
        $selectedRoomId = $request->input('room_id');
        if ($selectedRoomId && $selectedRoomId !== 'all') {
            $query->where('room_id', (int) $selectedRoomId);
        }

        // Filter: Status
        $selectedStatus = $request->input('status', 'all');
        if ($selectedStatus === 'paid') {
            $query->whereIn('status', [OrderStatus::Completed->value]);
        } elseif ($selectedStatus === 'unpaid') {
            $query->whereIn('status', OrderStatus::activeValues());
        } elseif ($selectedStatus === 'cancelled') {
            $query->where('status', OrderStatus::Cancelled);
        }

        // Filter: Time range
        $selectedTime = $request->input('time_range', 'this_month');
        $now = now();
        if ($selectedTime === 'this_month') {
            $query->where('created_at', '>=', $now->copy()->startOfMonth());
        } elseif ($selectedTime === 'last_month') {
            $query->where('created_at', '>=', $now->copy()->subMonthNoOverflow()->startOfMonth())
                ->where('created_at', '<', $now->copy()->startOfMonth());
        } elseif ($selectedTime === 'last_3_months') {
            $query->where('created_at', '>=', $now->copy()->subMonthsNoOverflow(3)->startOfMonth());
        }

        // Filter: Search query
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $stripped = preg_replace('/[^0-9]/', '', $search);
                if (! empty($stripped)) {
                    $q->orWhere('id', (int) $stripped);
                }
                $like = $this->getCaseInsensitiveLikeOperator();
                $q->orWhereHas('campaign', function ($cq) use ($search, $like) {
                    $cq->where('restaurant', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%");
                })->orWhereHas('items', function ($iq) use ($search, $like) {
                    $iq->where('item_name', $like, "%{$search}%");
                });
            });
        }

        $orders = $query->paginate(10)->withQueryString();

        // Calculate metrics for user
        $thisMonthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
        $thisMonthEnd = $now->copy()->addMonthNoOverflow()->startOfMonth();

        $allUserOrders = Order::query()->whereIn('room_user_id', $roomUserIds);
        $completedOrders = (clone $allUserOrders)->where('status', OrderStatus::Completed->value);
        $thisMonthOrders = (clone $completedOrders)
            ->where('created_at', '>=', $thisMonthStart)
            ->where('created_at', '<', $thisMonthEnd);
        $prevMonthOrdersCount = (clone $completedOrders)
            ->where('created_at', '>=', $prevMonthStart)
            ->where('created_at', '<', $thisMonthStart)
            ->count();

        $totalOrdersCount = (clone $thisMonthOrders)->count();
        $ordersDiff = $totalOrdersCount - $prevMonthOrdersCount;
        $totalSpent = (int) (clone $thisMonthOrders)->sum('final_amount');
        $totalSponsor = (int) (clone $thisMonthOrders)->sum('sponsor_amount');
        $pendingPaymentAmount = (int) (clone $allUserOrders)->whereIn('status', [OrderStatus::Submitted->value, OrderStatus::Confirmed->value])->sum('final_amount');
        $pendingPaymentCount = (clone $allUserOrders)->whereIn('status', [OrderStatus::Submitted->value, OrderStatus::Confirmed->value])->count();

        return [
            'user' => $user,
            'orders' => $orders,
            'userRooms' => $userRooms,
            'selectedRoomId' => $selectedRoomId,
            'selectedStatus' => $selectedStatus,
            'selectedTime' => $selectedTime,
            'search' => $search,
            'totalOrdersCount' => $totalOrdersCount,
            'ordersDiff' => $ordersDiff,
            'totalSpent' => $totalSpent,
            'totalSponsor' => $totalSponsor,
            'pendingPaymentAmount' => $pendingPaymentAmount,
            'pendingPaymentCount' => $pendingPaymentCount,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    /**
     * Truy vấn danh sách đơn hàng dạng phân trang cho JSON API client.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  Request  $request  Đối tượng HTTP Request chứa bộ lọc API
     * @return LengthAwarePaginator Đối tượng phân trang chứa danh sách đơn hàng
     */
    public function getOrdersApiData(GlobalUser $user, Request $request): LengthAwarePaginator
    {
        $roomUserIds = $user->roomUsers()->pluck('id');

        $query = Order::query()
            ->whereIn('room_user_id', $roomUserIds)
            ->with(['items.toppings', 'campaign', 'room', 'roomUser.globalUser', 'parent.roomUser.globalUser', 'children.roomUser.globalUser', 'children.items.toppings'])
            ->latest();

        if ($request->filled('room_id')) {
            $query->where('room_id', (int) $request->input('room_id'));
        }
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->input('campaign_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')?->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<', $request->date('to')?->addDay()->startOfDay());
        }

        return $query->paginate(20);
    }
}
