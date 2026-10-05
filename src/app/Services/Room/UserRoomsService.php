<?php

declare(strict_types=1);

namespace App\Services\Room;

use App\Enums\CampaignStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Support\Helpers\FormatHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class UserRoomsService
{
    /**
     * Thu thập và phân trang danh sách các phòng mà người dùng đã tham gia kèm bộ lọc và tìm kiếm.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  Request  $request  Đối tượng HTTP Request chứa các query params: search, filter, sort
     * @return array<string, mixed> Mảng dữ liệu cho View quản lý phòng
     */
    public function getRoomsData(GlobalUser $user, Request $request): array
    {
        // Summary counts for filter tabs
        $baseQuery = $user->roomUsers();
        $totalCount = (clone $baseQuery)->count();
        $activeCount = (clone $baseQuery)->where('status', RoomUserStatus::Active->value)->count();
        $restrictedCount = (clone $baseQuery)->where('status', RoomUserStatus::Blocked->value)->count();

        // Search, filter, and sort parameters
        $search = trim((string) $request->get('q', ''));
        $filter = $request->get('filter', 'all');
        $sort = $request->get('sort', 'latest');

        $query = $user->roomUsers()
            ->withCount('orders')
            ->withSum('orders', 'final_amount')
            ->withMax('orders', 'created_at')
            ->with([
                'room' => function ($q) {
                    $q->withCount(['roomUsers' => function ($ru) {
                        $ru->where('status', RoomUserStatus::Active->value);
                    }]);
                    $q->with(['campaigns' => function ($campaigns): void {
                        $campaigns->where('status', CampaignStatus::Active->value)
                            ->orderByDesc('started_at')
                            ->orderByDesc('id');
                    }]);
                },
            ]);

        // Filter by membership status
        if ($filter === 'active') {
            $query->where('status', RoomUserStatus::Active->value);
        } elseif ($filter === 'restricted' || $filter === 'blocked') {
            $query->where('status', RoomUserStatus::Blocked->value);
        }

        // Filter by search keyword (room name, slug, or numeric ID)
        if ($search !== '') {
            $query->whereHas('room', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // Sorting
        if ($sort === 'oldest') {
            $query->orderBy('joined_at', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'name_asc') {
            $query->join('rooms', 'room_users.room_id', '=', 'rooms.id')
                ->select('room_users.*')
                ->orderBy('rooms.name', 'asc');
        } elseif ($sort === 'spent_desc') {
            $query->select('room_users.*')
                ->selectSub(
                    Order::selectRaw('COALESCE(SUM(final_amount), 0)')
                        ->whereColumn('orders.room_user_id', 'room_users.id'),
                    'total_spent_sub'
                )
                ->orderByDesc('total_spent_sub');
        } else {
            // Default latest
            $query->orderByDesc('joined_at')->orderByDesc('id');
        }

        $paginator = $query->paginate(9)->withQueryString();

        // Map room users to display objects with formatted statistics
        $roomUsers = $paginator->through(fn (RoomUser $membership): object => $this->formatRoomCard($membership));

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.rooms.breadcrumb_rooms'), 'url' => route('user.me.rooms')],
        ];

        return [
            'user' => $user,
            'roomUsers' => $roomUsers,
            'paginator' => $paginator,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'restrictedCount' => $restrictedCount,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    /**
     * Format membership details and order statistics for the shared room card.
     *
     * @param  RoomUser  $membership  Membership with its room and order aggregates loaded.
     * @return object Display data for the room card.
     */
    public function formatRoomCard(RoomUser $membership): object
    {
        $ordersCount = (int) ($membership->orders_count ?? 0);
        $totalSpent = (int) ($membership->orders_sum_final_amount ?? 0);
        $lastOrderTime = null;
        if ($membership->orders_max_created_at) {
            $lastOrderTime = CarbonImmutable::parse((string) $membership->orders_max_created_at)->diffForHumans();
        } elseif ($membership->last_active_at) {
            $lastOrderTime = $membership->last_active_at->diffForHumans();
        }

        $statusVal = $membership->status instanceof RoomUserStatus ? $membership->status->value : (string) $membership->status;
        $isActive = $statusVal === RoomUserStatus::Active->value;
        $isBlocked = $statusVal === RoomUserStatus::Blocked->value;

        $roomSlug = $membership->room?->slug ?: ('room-'.$membership->room_id);
        $roomIdDisplay = 'ROOM-ID: '.strtoupper($roomSlug);
        $campaign = $membership->room?->campaigns?->first();
        $isLive = $campaign !== null;

        return (object) [
            'id' => $membership->id,
            'room' => $membership->room,
            'room_id' => $membership->room_id,
            'room_name' => $membership->room?->name ?? 'Room #'.$membership->room_id,
            'room_id_display' => $roomIdDisplay,
            'status' => $statusVal,
            'is_active' => $isActive,
            'is_blocked' => $isBlocked,
            'joined_at_formatted' => $membership->joined_at ? FormatHelper::formatDate($membership->joined_at) : ($membership->created_at ? FormatHelper::formatDate($membership->created_at) : 'N/A'),
            'orders_count' => $ordersCount,
            'total_spent' => $totalSpent,
            'total_spent_formatted' => FormatHelper::formatCurrency($totalSpent),
            'last_order_time' => $lastOrderTime,
            'dashboard_url' => $membership->room ? route('user.dashboard', $membership->room->slug ?? $membership->room->id) : '#',
            'is_live' => $isLive,
            'live_deadline' => $campaign?->deadline
                ? FormatHelper::formatDateTime($campaign->deadline, 'd/m/Y H:i')
                : null,
            'live_ordering_expired' => $campaign?->deadline?->isPast() ?? false,
        ];

    }

    /**
     * Tìm đối tượng phòng từ URL liên kết hoặc mã ID/slug nhập vào.
     *
     * @param  string  $inputUrl  Đường dẫn URL tham gia phòng (ví dụ: http://localhost:8080/rooms/tech-room)
     * @return Room|null Đối tượng Room tìm được hoặc null nếu không hợp lệ
     */
    public function resolveRoomFromUrl(string $inputUrl): ?Room
    {
        $path = parse_url($inputUrl, PHP_URL_PATH) ?? '';

        if (! preg_match('#/rooms/([^/]+)#', $path, $matches)) {
            return null;
        }

        $slugOrId = trim($matches[1]);
        $slugOrId = preg_replace('/^ROOM-ID:\s*/i', '', $slugOrId);
        $slugOrId = trim($slugOrId);

        $roomQuery = Room::query();
        if (is_numeric($slugOrId)) {
            $roomQuery->where('id', (int) $slugOrId)->orWhere('slug', $slugOrId);
        } else {
            $roomQuery->whereRaw('LOWER(slug) = ?', [strtolower($slugOrId)])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($slugOrId)]);
        }

        return $roomQuery->first();
    }
}
