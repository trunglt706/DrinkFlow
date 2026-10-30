<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\RoomStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Campaign\UserRoomCampaignService;
use App\Services\Reporting\SponsorLeaderboardService;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserRoomDashboardService
{
    /**
     * @param UserRoomCampaignService $campaignService Nguồn dữ liệu (đã cache) cho Top món được chọn nhiều nhất.
     * @param SponsorLeaderboardService $sponsorLeaderboard Resolves actual campaign sponsors (not subsidy beneficiaries).
     */
    public function __construct(
        private readonly UserRoomCampaignService $campaignService,
        private readonly SponsorLeaderboardService $sponsorLeaderboard,
        private readonly MonthlySponsorStatisticsService $monthlySponsors,
    ) {
    }

    /**
     * Aggregate all data required for the Room User Dashboard view.
     *
     * @param Room $room Current active room instance.
     * @param RoomUser $roomUser Current user's membership in this room.
     * @param GlobalUser|null $user Global authenticated user instance.
     * @return array<string, mixed> Keyed array of view parameters for the dashboard.
     */
    public function getDashboardData(Room $room, RoomUser $roomUser, ?GlobalUser $user): array
    {
        $activeCampaign = $room->campaigns()
            ->where('status', CampaignStatus::Active->value)
            ->first();

        $campaignData = null;
        if ($activeCampaign) {
            // Campaigns have no total sponsorship budget: only a policy (sponsor_type) and an optional
            // per-product cap (max_budget), the same values the campaign menu page shows.
            $sponsorUsed = (int) Order::where('campaign_id', $activeCampaign->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->sum('sponsor_amount');
            $popularItems = $this->campaignService->getFavoriteItems($activeCampaign, (int) $roomUser->global_user_id);

            $campaignData = [
                'id' => $activeCampaign->id,
                'name' => $activeCampaign->name,
                'restaurant' => $activeCampaign->restaurant ?? __('room.dashboard.partner'),
                'creator_name' => 'Admin',
                'description' => $activeCampaign->description ?? '',
                'deadline' => $activeCampaign->deadline,
                'time_remaining' => $activeCampaign->deadline ? ($activeCampaign->deadline->isFuture() ? $activeCampaign->deadline->diffForHumans(['parts' => 2, 'short' => true]) : '00:00') : '14:22',
                'deadline_formatted' => $activeCampaign->deadline ? FormatHelper::formatDateTime($activeCampaign->deadline, 'H:i') : '10:30',
                'sponsor_type' => $activeCampaign->sponsor_type ?: Campaign::SPONSOR_TYPE_NONE,
                'max_budget' => (int) ($activeCampaign->max_budget ?? 0),
                'sponsor_used' => $sponsorUsed,
                'popular_items' => $popularItems,
                'ordered_members' => $activeCampaign->orderedMembersCount(),
                'order_url' => route('user.campaigns.order-page', [$room->slug, $activeCampaign->id]),
                'has_ordered' => $roomUser->orders()
                    ->where('campaign_id', $activeCampaign->id)
                    ->where('status', '!=', OrderStatus::Cancelled->value)
                    ->exists(),
            ];
        }

        // Recent orders by this user in this room
        $userRecentOrders = $roomUser->orders()->where('status', '!=', OrderStatus::Cancelled->value)->with('items')->latest()->take(5)->get();

        // All active rooms of user for dropdown
        $userRooms = $user ? $user->rooms()->where('rooms.status', RoomStatus::Active->value)->get() : collect();

        // Unread notifications count
        $unreadCount = $user ? DB::table('user_notifications')
            ->where('global_user_id', $user->id)
            ->whereNull('read_at')
            ->count() : 0;

        return [
            'room' => $room,
            'roomUser' => $roomUser,
            'user' => $user,
            'activeCampaign' => $campaignData,
            'userRecentOrders' => $userRecentOrders,
            'userRooms' => $userRooms,
            'unreadNotificationsCount' => $unreadCount,
            'topSponsors' => $this->getTopSponsors($room),
            'sponsorHonors' => $this->monthlySponsors->previousMonthHonors($room),
            'monthlyItemTrend' => $this->getMonthlyItemTrend($room, $roomUser),
        ];
    }

    /**
     * Rank every actual sponsor of the room (people/entities who funded a campaign) for the current
     * calendar month, not the members who merely benefited from a sponsored order. Only completed
     * (finished) orders count toward the sponsored amount.
     *
     * @param Room $room Current active room instance.
     * @return array<int, array{name: string, amount: int, sponsored_campaigns: int}> All sponsors of the month, highest amount first.
     */
    private function getTopSponsors(Room $room): array
    {
        $from = Carbon::today()->startOfMonth();
        $to = Carbon::today()->endOfDay();
        $cacheKey = "room:{$room->id}:top_sponsors:{$to->toDateString()}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(2), function () use ($room, $from, $to): array {
            return $this->sponsorLeaderboard->build($room, $from, $to, null, [OrderStatus::Completed])
                ->map(fn (array $row): array => [
                    'name' => $row['user_name'],
                    'amount' => $row['total_sponsored'],
                    'sponsored_campaigns' => $row['sponsored_campaigns'],
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Daily item count and order value of completed orders from the first day of the current month
     * up to today, both for the whole room and for the current member only.
     *
     * @param Room $room Current active room instance.
     * @param RoomUser $roomUser Current user's membership, used for the personal series.
     * @return array{room: list<array<string, int|string>>, personal: list<array<string, int|string>>} Room-wide and personal series, see {@see buildDailyTrend()}.
     */
    private function getMonthlyItemTrend(Room $room, RoomUser $roomUser): array
    {
        $today = Carbon::today()->toDateString();

        return [
            'room' => \Illuminate\Support\Facades\Cache::remember(
                "room:{$room->id}:monthly_trend:{$today}",
                now()->addMinutes(2),
                fn (): array => $this->buildDailyTrend($room, null),
            ),
            'personal' => \Illuminate\Support\Facades\Cache::remember(
                "room:{$room->id}:member:{$roomUser->id}:monthly_trend:{$today}",
                now()->addMinutes(2),
                fn (): array => $this->buildDailyTrend($room, (int) $roomUser->id),
            ),
        ];
    }

    /**
     * Aggregate completed orders per day for the current month (day 1 through today).
     *
     * @param Room $room Room whose orders are aggregated.
     * @param int|null $roomUserId Restrict to one member's orders; null = the whole room.
     * @return list<array{date: string, day: string, day_name: string, items_count: int, value_amount: int}> One entry per day, oldest first.
     */
    private function buildDailyTrend(Room $room, ?int $roomUserId): array
    {
        $today = Carbon::today();
        $from = $today->copy()->startOfMonth();
        $to = $today->copy()->endOfDay();

        $dayLabels = [
            1 => __('room.dashboard.day_monday'),
            2 => __('room.dashboard.day_tuesday'),
            3 => __('room.dashboard.day_wednesday'),
            4 => __('room.dashboard.day_thursday'),
            5 => __('room.dashboard.day_friday'),
            6 => __('room.dashboard.day_saturday'),
            0 => __('room.dashboard.day_sunday'),
        ];

        $dailySubtotals = Order::query()
            ->selectRaw('DATE(created_at) as date_key, SUM(subtotal) as total_subtotal')
            ->where('room_id', $room->id)
            ->when($roomUserId !== null, fn ($query) => $query->where('room_user_id', $roomUserId))
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('status', OrderStatus::Completed->value)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total_subtotal', 'date_key');

        $dailyQuantities = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('DATE(orders.created_at) as date_key, SUM(order_items.quantity) as total_quantity')
            ->where('orders.room_id', $room->id)
            ->when($roomUserId !== null, fn ($query) => $query->where('orders.room_user_id', $roomUserId))
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<=', $to)
            ->where('orders.status', OrderStatus::Completed->value)
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->pluck('total_quantity', 'date_key');

        $trend = [];
        for ($currentDate = $from->copy(); $currentDate->lte($today); $currentDate->addDay()) {
            $dateKey = $currentDate->toDateString();
            $dayOfWeek = (int) $currentDate->format('w');

            $trend[] = [
                'date' => FormatHelper::formatDate($currentDate, 'd/m'),
                'day' => $currentDate->format('j'),
                'day_name' => $currentDate->isSameDay($today) ? __('room.dashboard.day_today') : ($dayLabels[$dayOfWeek] ?? $currentDate->format('D')),
                'items_count' => (int) ($dailyQuantities[$dateKey] ?? 0),
                'value_amount' => (int) ($dailySubtotals[$dateKey] ?? 0),
            ];
        }

        return $trend;
    }
}
