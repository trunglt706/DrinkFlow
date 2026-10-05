<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Support\Helpers\FormatHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserGlobalAnalyticsService
{
    /** @return array<string, mixed> */
    public function getAnalyticsViewData(GlobalUser $user): array
    {
        $metrics = (clone $this->completedOrders($user))
            ->selectRaw('COUNT(*) AS total_orders')
            ->selectRaw('COALESCE(SUM(final_amount), 0) AS total_spent')
            ->selectRaw('COALESCE(SUM(sponsor_amount), 0) AS total_sponsor')
            ->first();
        $totalOrders = (int) ($metrics?->total_orders ?? 0);
        $totalSpent = (int) ($metrics?->total_spent ?? 0);
        $totalSponsor = (int) ($metrics?->total_sponsor ?? 0);
        $avgSpent = $totalOrders > 0 ? (int) round($totalSpent / $totalOrders) : 0;
        $sponsorPercent = ($totalSpent + $totalSponsor) > 0
            ? (int) round(($totalSponsor / ($totalSpent + $totalSponsor)) * 100)
            : 0;

        $topItems = $this->topItems($user, 10);
        $topItem = $topItems->first();
        $favoriteItem = $topItem?->name ?? __('global.statistics.no_favorite_item');
        $favoriteItemOrders = (int) ($topItem?->quantity ?? 0);
        $totalItemQuantity = $this->totalItemQuantity($user);
        $colors = ['#006948', '#64748b', '#f59e0b', '#0284c7'];
        $categoryStats = $topItems->take(3)->values()->map(
            static fn (object $item, int $index): array => [
                'name' => $item->name,
                'percent' => $totalItemQuantity > 0 ? (int) round(((int) $item->quantity / $totalItemQuantity) * 100) : 0,
                'color' => $colors[$index % count($colors)],
                'quantity' => (int) $item->quantity,
            ]
        )->all();

        $weeklyStats = $this->weeklyStats($user);
        $allAmounts = array_merge(array_column($weeklyStats, 'spent'), array_column($weeklyStats, 'sponsor'));
        $maxWeeklySpent = max(1, ...$allAmounts);
        foreach ($weeklyStats as $key => $week) {
            $weeklyStats[$key]['height_spent'] = $week['spent'] > 0 ? max(8, (int) round(($week['spent'] / $maxWeeklySpent) * 85)) : 0;
            $weeklyStats[$key]['height_sponsor'] = $week['sponsor'] > 0 ? max(8, (int) round(($week['sponsor'] / $maxWeeklySpent) * 85)) : 0;
        }
        $peakWeek = collect($weeklyStats)->sortByDesc('spent')->first();
        $peakWeekText = ($peakWeek && $peakWeek['spent'] > 0)
            ? __('global.statistics.peak_week_text', [
                'amount' => FormatHelper::formatCurrency((int) $peakWeek['spent']),
                'suffix' => '',
                'week' => explode(' ', $peakWeek['label'])[0],
            ])
            : __('global.statistics.no_expense_in_period');

        $roomStats = DB::table('orders')
            ->join('room_users', 'room_users.id', '=', 'orders.room_user_id')
            ->join('rooms', 'rooms.id', '=', 'room_users.room_id')
            ->where('room_users.global_user_id', $user->id)
            ->where('room_users.status', RoomUserStatus::Active->value)
            ->where('orders.status', OrderStatus::Completed->value)
            ->groupBy('rooms.id', 'rooms.name', 'rooms.description')
            ->selectRaw('rooms.name AS name, rooms.description AS location, COUNT(orders.id) AS order_count')
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) AS spent, COALESCE(SUM(orders.sponsor_amount), 0) AS sponsor')
            ->orderByDesc('spent')
            ->get()
            ->map(static fn (object $room): array => [
                'name' => $room->name,
                'location' => $room->location ?: __('global.statistics.default_workspace_desc'),
                'order_count' => (int) $room->order_count,
                'spent' => (int) $room->spent,
                'sponsor' => (int) $room->sponsor,
            ])->all();

        $topRestaurants = DB::table('orders')
            ->join('room_users', 'room_users.id', '=', 'orders.room_user_id')
            ->join('campaigns', 'campaigns.id', '=', 'orders.campaign_id')
            ->where('room_users.global_user_id', $user->id)
            ->where('room_users.status', RoomUserStatus::Active->value)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNotNull('campaigns.restaurant')
            ->where('campaigns.restaurant', '!=', '')
            ->groupBy('campaigns.restaurant')
            ->selectRaw('campaigns.restaurant AS name, COUNT(orders.id) AS order_count, COALESCE(SUM(orders.final_amount), 0) AS spent')
            ->orderByDesc('order_count')
            ->limit(4)
            ->get()
            ->values()
            ->map(static fn (object $restaurant, int $index): array => [
                'rank' => $index + 1,
                'name' => $restaurant->name,
                'sub_title' => __('global.statistics.popular_beverages'),
                'order_count' => (int) $restaurant->order_count,
                'spent' => (int) $restaurant->spent,
            ])->all();

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.statistics.breadcrumb_stats'), 'url' => route('user.me.statistics')],
        ];

        return compact(
            'user', 'totalOrders', 'totalSpent', 'avgSpent', 'totalSponsor', 'sponsorPercent',
            'favoriteItem', 'favoriteItemOrders', 'weeklyStats', 'peakWeekText', 'categoryStats',
            'roomStats', 'topRestaurants', 'breadcrumbs',
        );
    }

    /** @return array<string, mixed> */
    public function getAnalyticsApiData(GlobalUser $user): array
    {
        $metrics = (clone $this->completedOrders($user))
            ->selectRaw('COUNT(*) AS total_orders')
            ->selectRaw('COALESCE(SUM(final_amount), 0) AS total_amount')
            ->selectRaw('COALESCE(SUM(sponsor_amount), 0) AS sponsor_received')
            ->first();

        return [
            'total_orders' => (int) ($metrics?->total_orders ?? 0),
            'total_cups' => $this->totalItemQuantity($user),
            'total_amount' => (int) ($metrics?->total_amount ?? 0),
            'sponsor_received' => (int) ($metrics?->sponsor_received ?? 0),
            'top_items' => $this->topItems($user, 10)->map(static fn (object $item): array => [
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
            ])->values(),
        ];
    }

    /** @return Builder<Order> */
    private function completedOrders(GlobalUser $user): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereIn('room_user_id', $user->roomUsers()
                ->select('room_users.id')
                ->where('status', RoomUserStatus::Active->value));
    }

    /** @return Collection<int, object> */
    private function topItems(GlobalUser $user, int $limit): Collection
    {
        return DB::table('order_items')
            ->joinSub((clone $this->completedOrders($user))->select('orders.id'), 'completed_orders', 'completed_orders.id', '=', 'order_items.order_id')
            ->groupBy('order_items.item_name')
            ->selectRaw('order_items.item_name AS name, COALESCE(SUM(order_items.quantity), 0) AS quantity')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    private function totalItemQuantity(GlobalUser $user): int
    {
        return (int) DB::table('order_items')
            ->joinSub((clone $this->completedOrders($user))->select('orders.id'), 'completed_orders', 'completed_orders.id', '=', 'order_items.order_id')
            ->sum('order_items.quantity');
    }

    /** @return array<int, array{label: string, spent: int, sponsor: int, height_spent: int, height_sponsor: int}> */
    private function weeklyStats(GlobalUser $user): array
    {
        $firstWeekStart = CarbonImmutable::now(config('app.timezone'))->startOfWeek()->subWeeks(3);
        $bindings = [];
        $selects = [];
        for ($week = 1; $week <= 4; $week++) {
            $start = $firstWeekStart->addWeeks($week - 1);
            $end = $start->addWeek();
            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN final_amount ELSE 0 END), 0) AS spent_{$week}";
            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN sponsor_amount ELSE 0 END), 0) AS sponsor_{$week}";
            array_push($bindings, $start, $end, $start, $end);
        }

        $row = (clone $this->completedOrders($user))->selectRaw(implode(', ', $selects), $bindings)->first();
        $result = [];
        for ($week = 1; $week <= 4; $week++) {
            $result[$week] = [
                'label' => __('global.statistics.week_'.$week.'_label'),
                'spent' => (int) ($row->{"spent_{$week}"} ?? 0),
                'sponsor' => (int) ($row->{"sponsor_{$week}"} ?? 0),
                'height_spent' => 0,
                'height_sponsor' => 0,
            ];
        }

        return $result;
    }
}
