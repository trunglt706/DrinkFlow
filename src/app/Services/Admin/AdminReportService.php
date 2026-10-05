<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Services\Reporting\PaymentAccountReportService;
use App\Services\Reporting\SponsorLeaderboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminReportService
{
    /**
     * @param SponsorLeaderboardService $sponsorLeaderboard Resolves actual campaign sponsors (not subsidy beneficiaries).
     * @param PaymentAccountReportService $paymentAccountReport Order count and received/outstanding money per receiving account.
     */
    public function __construct(
        private readonly SponsorLeaderboardService $sponsorLeaderboard,
        private readonly PaymentAccountReportService $paymentAccountReport,
    ) {
    }

    /**
     * Compute lightweight KPI summary stats for a room.
     *
     * @param Request $request Incoming HTTP request containing period or date range.
     * @param Room $room Room entity.
     * @return array<string, mixed> Summary KPI statistics.
     */
    public function getReportStats(Request $request, Room $room): array
    {
        [$from, $to] = $this->period($request, $room->timezone ?? config('app.timezone'));

        $orders = Order::query()
            ->where('orders.room_id', $room->id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value]);

        $campaigns = Campaign::query()
            ->where('campaigns.room_id', $room->id)
            ->whereBetween('campaigns.created_at', [$from, $to]);

        $debts = Debt::query()
            ->where('debts.room_id', $room->id)
            ->whereBetween('debts.created_at', [$from, $to]);

        return [
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'campaign_count' => (clone $campaigns)->count(),
            'order_count' => (clone $orders)->count(),
            'spending' => (int) (clone $orders)->sum('final_amount'),
            'sponsor_amount' => (int) (clone $orders)->sum('sponsor_amount'),
            'debt' => (int) (clone $debts)->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])->sum('remaining_amount'),
            'debt_total' => (int) (clone $debts)->sum('original_amount'),
            'debt_paid' => (int) (clone $debts)->sum('paid_amount'),
            'user_participation' => (int) (clone $orders)->distinct('room_user_id')->count('room_user_id'),
        ];
    }

    /**
     * Compute report analytics and metrics for a room, optionally lazy-loading specific tab data.
     *
     * @param Request $request Incoming HTTP request containing period, date range, and optional tab.
     * @param Room $room Room entity.
     * @return array<string, mixed> Analytics payload.
     */
    public function getReportMetrics(Request $request, Room $room): array
    {
        [$from, $to] = $this->period($request, $room->timezone ?? config('app.timezone'));
        $tab = $request->string('tab')->trim()->toString();

        $stats = $this->getReportStats($request, $room);
        $payload = $stats;

        // If specific tab requested, only compute what is needed for that tab
        if ($tab === 'products' || empty($tab) || $tab === 'all') {
            $orders = Order::query()
                ->where('orders.room_id', $room->id)
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereNotIn('orders.status', [OrderStatus::Cancelled->value]);

            $payload['popular_drinks'] = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.room_id', $room->id)
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereNotIn('orders.status', [OrderStatus::Cancelled->value])
                ->selectRaw('order_items.item_name, SUM(order_items.quantity) as quantity')
                ->groupBy('order_items.item_name')
                ->orderByDesc('quantity')
                ->limit(5)
                ->get();

            $payload['popular_stores'] = (clone $orders)
                ->join('campaigns', 'campaigns.id', '=', 'orders.campaign_id')
                ->selectRaw('campaigns.restaurant, COUNT(orders.id) as orders, SUM(orders.final_amount) as spending')
                ->groupBy('campaigns.restaurant')
                ->orderByDesc('orders')
                ->limit(5)
                ->get();
        }

        if ($tab === 'debts' || empty($tab) || $tab === 'all') {
            $payload['debts_by_user'] = Debt::query()
                ->where('debts.room_id', $room->id)
                ->whereBetween('debts.created_at', [$from, $to])
                ->join('room_users', 'room_users.id', '=', 'debts.room_user_id')
                ->leftJoin('global_users', 'global_users.id', '=', 'room_users.global_user_id')
                ->selectRaw('
                    debts.room_user_id,
                    COALESCE(global_users.name, room_users.display_name) as user_name,
                    global_users.email as user_email,
                    COUNT(debts.id) as debt_count,
                    SUM(debts.original_amount) as total_original,
                    SUM(debts.paid_amount) as total_paid,
                    SUM(debts.remaining_amount) as total_remaining,
                    SUM(CASE WHEN debts.status IN (?, ?) THEN debts.remaining_amount ELSE 0 END) as outstanding_debt
                ', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
                ->groupBy('debts.room_user_id', 'global_users.name', 'room_users.display_name', 'global_users.email')
                ->having('outstanding_debt', '>', 0)
                ->orderByDesc('outstanding_debt')
                ->orderByDesc('total_original')
                ->get();
        }

        if ($tab === 'sponsors' || empty($tab) || $tab === 'all') {
            $payload['sponsors_leaderboard'] = $this->sponsorLeaderboard->build($room, $from, $to);
        }

        if ($tab === 'accounts' || empty($tab) || $tab === 'all') {
            $payload['payment_accounts'] = $this->paymentAccountReport->build($room, $from, $to);
        }

        if ($tab === 'users' || empty($tab) || $tab === 'all') {
            $payload['top_users'] = Order::query()
                ->where('orders.room_id', $room->id)
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereNotIn('orders.status', [OrderStatus::Cancelled->value])
                ->join('room_users', 'room_users.id', '=', 'orders.room_user_id')
                ->leftJoin('global_users', 'global_users.id', '=', 'room_users.global_user_id')
                ->selectRaw('
                    orders.room_user_id,
                    COALESCE(global_users.name, room_users.display_name) as user_name,
                    global_users.email as user_email,
                    COUNT(orders.id) as order_count,
                    SUM(orders.final_amount) as total_spent
                ')
                ->groupBy('orders.room_user_id', 'global_users.name', 'room_users.display_name', 'global_users.email')
                ->orderByDesc('order_count')
                ->limit(10)
                ->get();
        }

        return $payload;
    }

    /**
     * Compute date period bounds based on request parameters.
     *
     * Explicit date_from/date_to win; otherwise the `period` preset is used, defaulting to the last
     * 7 days (today included). `period=all` covers the whole history.
     *
     * @param Request $request Incoming request.
     * @param string $timezone Timezone string.
     * @return array{0: Carbon, 1: Carbon} Start and end dates.
     */
    public function period(Request $request, string $timezone): array
    {
        $now = Carbon::now($timezone ?: config('app.timezone'));

        $fromInput = $request->input('date_from') ?: $request->input('from');
        $toInput = $request->input('date_to') ?: $request->input('to');

        if (!empty($fromInput) || !empty($toInput)) {
            try {
                $start = !empty($fromInput)
                    ? Carbon::parse((string) $fromInput, $timezone)->startOfDay()
                    : Carbon::parse('2020-01-01', $timezone)->startOfDay();
            } catch (\Throwable) {
                $start = $now->copy()->subDays(6)->startOfDay();
            }

            try {
                $end = !empty($toInput)
                    ? Carbon::parse((string) $toInput, $timezone)->endOfDay()
                    : $now->copy()->endOfDay();
            } catch (\Throwable) {
                $end = $now->copy()->endOfDay();
            }

            return [$start, $end];
        }

        $start = match ($request->string('period', '7days')->toString()) {
            'today' => $now->copy()->startOfDay(),
            'yesterday' => $now->copy()->subDay()->startOfDay(),
            '7days', 'last_7_days', 'week' => $now->copy()->subDays(6)->startOfDay(),
            '30days', 'last_30_days' => $now->copy()->subDays(29)->startOfDay(),
            'quarter' => $now->copy()->firstOfQuarter()->startOfDay(),
            'year' => $now->copy()->startOfYear(),
            'all' => Carbon::parse('2020-01-01', $timezone)->startOfDay(),
            default => $now->copy()->startOfMonth(),
        };

        return [$start, $now->copy()->endOfDay()];
    }
}
