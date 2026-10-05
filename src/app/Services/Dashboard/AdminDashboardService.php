<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\RoomUserStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use App\Support\Helpers\FormatHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class AdminDashboardService
{
    public function __construct(private readonly MonthlySponsorStatisticsService $monthlySponsors)
    {
    }

    /**
     * Get aggregated data for Admin Dashboard Blade view.
     *
     * @param Room $room Room entity.
     * @param ?Admin $admin Current logged-in admin account.
     * @return array<string, mixed> Aggregated page payload.
     */
    public function getDashboardPageData(Room $room, ?Admin $admin): array
    {
        $assignedRooms = $admin?->rooms()->get() ?: collect([$room]);
        if ($assignedRooms->isEmpty()) {
            $assignedRooms = collect([$room]);
        }

        $metrics = $this->getDashboardMetrics($room);

        $activePaymentAccount = PaymentAccount::query()
            ->where('room_id', $room->id)
            ->where('status', PaymentAccountStatus::Active)
            ->first();

        return [
            'room' => $room,
            'sponsorHonors' => $this->monthlySponsors->previousMonthHonors($room),
            'assignedRooms' => $assignedRooms,
            'activeRoomsCount' => $metrics['active_rooms'],
            'ordersTodayCount' => $metrics['orders_today'],
            'ordersGrowth' => $metrics['orders_growth'],
            'todayTotalValue' => $metrics['today_total_value'],
            'todaySponsorValue' => $metrics['today_sponsor_value'],
            'outstandingDebtsTotal' => $metrics['outstanding_debts'],
            'outstandingDebtsCount' => $metrics['outstanding_debts_count'],
            'pendingDebtUsersCount' => $metrics['pending_debt_users_count'],
            'activeCampaignsCount' => $metrics['active_campaigns_count'],
            'activeParticipants' => $metrics['active_participants'],
            'activeRoomUsers' => $metrics['active_room_users'],
            'debtCollectionRate' => $metrics['debt_collection_rate'],
            'activeCampaign' => $metrics['active_campaign'],
            'secondaryCampaign' => $metrics['secondary_campaign'],
            'weeklyTrend' => $metrics['weekly_trend'],
            'totalWeekCampaigns' => $metrics['weekly_total_campaigns'],
            'totalWeekSpending' => $metrics['weekly_total_spending'],
            'maxSpendingDayIndex' => $metrics['max_spending_day_index'],
            'activePaymentAccount' => $activePaymentAccount,
        ];
    }

    /**
     * Calculate core metrics for both API and Page view with short cache.
     *
     * @param Room $room Room entity.
     * @param bool $skipCache Force calculate fresh metrics.
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(Room $room, bool $skipCache = false): array
    {
        if ($skipCache) {
            return $this->calculateMetrics($room);
        }

        return Cache::remember(
            "admin:dashboard:metrics:{$room->id}",
            30,
            fn (): array => $this->calculateMetrics($room)
        );
    }

    /**
     * Internal calculation of dashboard metrics using efficient range queries.
     *
     * @param Room $room Room entity.
     * @return array<string, mixed>
     */
    private function calculateMetrics(Room $room): array
    {
        $today = Carbon::today();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();
        $yesterdayStart = $today->copy()->subDay()->startOfDay();
        $yesterdayEnd = $today->copy()->subDay()->endOfDay();
        $sevenDaysAgoStart = $today->copy()->subDays(6)->startOfDay();

        $orders = Order::query()->where('room_id', $room->id);
        $debts = Debt::query()->where('room_id', $room->id);

        $campaignMetrics = static fn (HasMany $query): HasMany => $query
            ->withCount([
                'orders' => static fn (Builder $orderQuery): Builder => $orderQuery
                    ->where('status', '!=', OrderStatus::Cancelled->value),
            ])
            ->withSum([
                'orders as total_amount' => static fn (Builder $orderQuery): Builder => $orderQuery
                    ->where('status', '!=', OrderStatus::Cancelled->value),
            ], 'final_amount')
            ->withSum([
                'orders as sponsor_total' => static fn (Builder $orderQuery): Builder => $orderQuery
                    ->where('status', '!=', OrderStatus::Cancelled->value),
            ], 'sponsor_amount');

        $activeCampaigns = $campaignMetrics($room->campaigns()->where('status', CampaignStatus::Active))
            ->latest('started_at')
            ->get();

        $activeCampaign = $activeCampaigns->first();
        $secondaryCampaign = $activeCampaigns->count() > 1 ? $activeCampaigns->get(1) : null;

        $completedStatus = OrderStatus::Completed->value;
        $ordersTodayCount = (clone $orders)->whereBetween('created_at', [$todayStart, $todayEnd])->where('status', $completedStatus)->count();
        $ordersYesterdayCount = (clone $orders)->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->where('status', $completedStatus)->count();
        $ordersGrowth = $ordersYesterdayCount > 0
            ? (int) round((($ordersTodayCount - $ordersYesterdayCount) / $ordersYesterdayCount) * 100)
            : ($ordersTodayCount > 0 ? 100 : 0);

        $todayTotalValue = (int) (clone $orders)->whereBetween('created_at', [$todayStart, $todayEnd])->where('status', $completedStatus)->sum('final_amount');
        $todaySponsorValue = (int) (clone $orders)->whereBetween('created_at', [$todayStart, $todayEnd])->where('status', $completedStatus)->sum('sponsor_amount');

        $pendingDebtUsersCount = (clone $debts)->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])->distinct('room_user_id')->count('room_user_id');

        // Weekly trend aggregated queries (2 queries instead of 14 queries inside loop)
        $campaignCountsByDay = $room->campaigns()
            ->whereBetween('created_at', [$sevenDaysAgoStart, $todayEnd])
            ->selectRaw('DATE(created_at) as date_val, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $orderCountsByDay = Order::query()
            ->where('room_id', $room->id)
            ->whereBetween('created_at', [$sevenDaysAgoStart, $todayEnd])
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('DATE(created_at) as date_val, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $weeklyGrossByDay = $this->weeklyGrossSpendingByDay($room, $sevenDaysAgoStart, $todayEnd);

        $weeklyTrend = [];
        $maxSpending = 0;
        $maxDayIndex = -1;
        $totalWeekCampaigns = 0;
        $totalWeekSpending = 0;

        $dayLabels = [
            1 => __('admin.day_monday'),
            2 => __('admin.day_tuesday'),
            3 => __('admin.day_wednesday'),
            4 => __('admin.day_thursday'),
            5 => __('admin.day_friday'),
            6 => __('admin.day_saturday'),
            0 => __('admin.day_sunday'),
        ];

        for ($i = 6; $i >= 0; $i--) {
            $currentDate = $today->copy()->subDays($i);
            $dateString = $currentDate->toDateString();
            $dayOfWeek = (int) $currentDate->format('w');
            $dayLabel = $i === 0 ? __('admin.day_today') : ($dayLabels[$dayOfWeek] ?? $currentDate->format('D'));

            $cCount = (int) ($campaignCountsByDay[$dateString] ?? 0);
            $sAmount = (int) ($weeklyGrossByDay[$dateString] ?? 0);
            $oCount = (int) ($orderCountsByDay[$dateString] ?? 0);

            $totalWeekCampaigns += $cCount;
            $totalWeekSpending += $sAmount;

            if ($sAmount > $maxSpending) {
                $maxSpending = $sAmount;
                $maxDayIndex = 6 - $i;
            }

            $weeklyTrend[] = [
                'date' => FormatHelper::formatDate($currentDate, 'd/m'),
                'day_name' => $dayLabel,
                'campaigns_count' => $cCount,
                'spending_amount' => $sAmount,
                'orders_count' => $oCount,
                'is_peak' => false,
            ];
        }

        if ($maxDayIndex >= 0) {
            $weeklyTrend[$maxDayIndex]['is_peak'] = true;
        }

        // Active participants in active campaigns
        $activeParticipants = 0;
        if ($activeCampaign) {
            $activeParticipants = Order::query()
                ->where('campaign_id', $activeCampaign->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->distinct('room_user_id')
                ->count('room_user_id');
        }

        // Active members of the room
        $activeRoomUsers = RoomUser::query()
            ->where('room_id', $room->id)
            ->where('status', RoomUserStatus::Active->value)
            ->count();

        // Debt collection rate
        $totalDebtsAmount = (int) (clone $debts)->sum('original_amount');
        $paidDebtsAmount = (int) (clone $debts)->sum('paid_amount');
        $debtCollectionRate = $totalDebtsAmount > 0
            ? round(($paidDebtsAmount / $totalDebtsAmount) * 100, 1)
            : 100;

        return [
            'active_rooms' => 1,
            'orders_today' => $ordersTodayCount,
            'orders_growth' => $ordersGrowth,
            'today_total_value' => $todayTotalValue,
            'today_sponsor_value' => $todaySponsorValue,
            'outstanding_debts' => (int) (clone $debts)->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])->sum('remaining_amount'),
            'outstanding_debts_count' => (clone $debts)->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])->count(),
            'pending_debt_users_count' => $pendingDebtUsersCount,
            'active_campaigns' => $activeCampaigns->count(),
            'active_campaigns_count' => $activeCampaigns->count(),
            'active_participants' => $activeParticipants,
            'active_room_users' => $activeRoomUsers,
            'debt_collection_rate' => $debtCollectionRate,
            'active_campaign' => $activeCampaign,
            'secondary_campaign' => $secondaryCampaign,
            'weekly_trend' => $weeklyTrend,
            'weekly_total_campaigns' => $totalWeekCampaigns,
            'weekly_total_spending' => $totalWeekSpending,
            'max_spending_day_index' => $maxDayIndex,
        ];
    }

    /**
     * Gross spending per day for the weekly trend: each campaign created in the window adds its gross total
     * (non-cancelled order subtotals + delivery fee - discount, before sponsorship) to the day it was created.
     *
     * @param Room $room Room scope.
     * @param Carbon $from Window start (inclusive).
     * @param Carbon $to Window end (inclusive).
     * @return array<string, int> Gross total in VND keyed by Y-m-d.
     */
    private function weeklyGrossSpendingByDay(Room $room, Carbon $from, Carbon $to): array
    {
        return $room->campaigns()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value])
            ->withSum([
                'orders as gross_subtotal' => static fn (Builder $query): Builder => $query
                    ->where('status', '!=', OrderStatus::Cancelled->value),
            ], 'subtotal')
            ->get()
            ->filter(static fn (Campaign $campaign): bool => (int) $campaign->gross_subtotal > 0)
            ->groupBy(static fn (Campaign $campaign): string => $campaign->created_at->toDateString())
            ->map(static fn ($campaigns): int => (int) $campaigns->sum(
                static fn (Campaign $campaign): int => $campaign->grossTotal((int) $campaign->gross_subtotal)
            ))
            ->all();
    }
}
