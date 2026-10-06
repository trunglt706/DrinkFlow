<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\AdminStatus;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\SecurityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * System-wide analytics for the superadmin dashboard: period KPIs with deltas, daily order/GMV
 * series, debt aging, a per-room health table with warning flags and campaigns stuck past their deadline.
 *
 * All "day" buckets use the application timezone. Queries stay portable between MySQL and SQLite
 * (date boundaries are computed in PHP and bound as parameters).
 */
class SuperadminDashboardService
{
    /** Length in days of the rolling period the KPIs and series cover. */
    public const PERIOD_DAYS = 30;

    /** A room with no campaign for this many days is flagged as dormant. */
    public const DORMANT_DAYS = 30;

    /** Outstanding debt older than this many days counts as overdue. */
    public const OVERDUE_DEBT_DAYS = 30;

    /** Share of outstanding debt that is overdue above which a room is flagged. */
    public const BAD_DEBT_RATIO = 0.3;

    /** Share of cancelled campaigns in the period above which a room is flagged. */
    public const HIGH_CANCEL_RATIO = 0.2;

    /** Minimum campaigns in the period before the cancel ratio is judged (avoids noise on tiny rooms). */
    public const HIGH_CANCEL_MIN_CAMPAIGNS = 3;

    /** Hours past its deadline after which a still-open campaign is reported as stuck. */
    public const STUCK_CAMPAIGN_GRACE_HOURS = 2;

    /** Maximum stuck campaigns returned. */
    public const STUCK_CAMPAIGN_LIMIT = 20;

    /** Seconds the computed analytics are cached. */
    public const CACHE_TTL = 300;

    public const CACHE_KEY = 'superadmin.dashboard.analytics';

    /** Security event severity counted by the high-severity KPI. */
    public const SECURITY_SEVERITY_HIGH = 'high';

    public const FLAG_DORMANT = 'dormant';
    public const FLAG_BAD_DEBT = 'bad_debt';
    public const FLAG_NO_ADMIN = 'no_admin';
    public const FLAG_HIGH_CANCEL = 'high_cancel';

    /** Debt aging buckets: key => [min age in days (inclusive), max age in days (inclusive) or null]. */
    public const AGING_BUCKETS = [
        '0_7' => [0, 7],
        '8_30' => [8, 30],
        '31_60' => [31, 60],
        '60_plus' => [61, null],
    ];

    /**
     * Build (or read from cache) the full analytics payload.
     *
     * @param bool $fresh Bypass and refresh the cache.
     * @return array<string, mixed> Payload with keys kpis, daily, debt_aging, rooms, stuck_campaigns, generated_at.
     */
    public function analytics(bool $fresh = false): array
    {
        return $this->analyticsFor(null, true, $fresh);
    }

    /**
     * Analytics restricted to some rooms (a Superadmin whose room scope is "managed Agents").
     *
     * Every operational figure (KPIs, daily series, debt aging, room health, stuck campaigns) only counts
     * the given rooms; the platform-wide security KPI is left out (null) unless asked for. Each room set is
     * cached under its own key, so one Superadmin never reads another scope's figures.
     *
     * @param list<int>|null $roomIds Rooms to count; null = every room of the platform.
     * @param bool $includeSecurity Include the high-severity security KPI (platform-wide figure).
     * @param bool $fresh Bypass and refresh the cache.
     * @return array<string, mixed> Payload with keys kpis, daily, debt_aging, rooms, stuck_campaigns, generated_at.
     */
    public function analyticsFor(?array $roomIds, bool $includeSecurity, bool $fresh = false): array
    {
        $ids = $roomIds === null ? null : array_values(array_unique(array_map('intval', $roomIds)));
        if ($ids !== null) {
            sort($ids);
        }
        $key = self::CACHE_KEY.($ids === null ? '' : ':rooms:'.sha1(implode(',', $ids))).($includeSecurity ? '' : ':nosec');
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_TTL, function () use ($ids, $includeSecurity): array {
            $payload = $this->compute(CarbonImmutable::now(), $ids);
            if (! $includeSecurity) {
                $payload['kpis']['high_security_events'] = ['value' => null, 'previous' => null];
            }

            return $payload;
        });
    }

    /** Rooms the current computation is restricted to (null = all rooms). */
    private ?array $roomIds = null;

    /**
     * Restrict a query to the rooms of the current computation.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query Query.
     * @param string $column Room ID column.
     * @return Builder<\Illuminate\Database\Eloquent\Model> Restricted query.
     */
    private function inScope(Builder $query, string $column): Builder
    {
        return $this->roomIds === null ? $query : $query->whereIn($column, $this->roomIds);
    }

    /**
     * Compute the analytics payload at a given moment.
     *
     * @param CarbonImmutable $now Reference time.
     * @param list<int>|null $roomIds Rooms to count; null = every room.
     * @return array<string, mixed> Analytics payload.
     */
    public function compute(CarbonImmutable $now, ?array $roomIds = null): array
    {
        $this->roomIds = $roomIds;

        try {
            return $this->computePayload($now);
        } finally {
            $this->roomIds = null;
        }
    }

    /**
     * @param CarbonImmutable $now Reference time.
     * @return array<string, mixed> Analytics payload for the current room restriction.
     */
    private function computePayload(CarbonImmutable $now): array
    {
        return [
            'period_days' => self::PERIOD_DAYS,
            'kpis' => $this->kpis($now),
            'daily' => $this->dailySeries($now),
            'debt_aging' => $this->debtAging($now),
            'rooms' => $this->roomHealth($now),
            'stuck_campaigns' => $this->stuckCampaigns($now),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * KPIs for the current period compared with the previous period of equal length.
     *
     * @param CarbonImmutable $now Reference time.
     * @return array<string, array{value: int|float|null, previous: int|float|null}> KPI values.
     */
    public function kpis(CarbonImmutable $now): array
    {
        $start = $now->subDays(self::PERIOD_DAYS);
        $previousStart = $start->subDays(self::PERIOD_DAYS);

        $activeRooms = fn(CarbonImmutable $from, CarbonImmutable $to): int => $this->inScope(Campaign::query(), 'room_id')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->distinct()->count('room_id');

        $activeUsers = fn(CarbonImmutable $from, CarbonImmutable $to): int => $this->validOrders()
            ->join('room_users', 'room_users.id', '=', 'orders.room_user_id')
            ->where('orders.created_at', '>=', $from)->where('orders.created_at', '<', $to)
            ->distinct()->count('room_users.global_user_id');

        $gmv = fn(CarbonImmutable $from, CarbonImmutable $to): int => (int) $this->validOrders()
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->sum('final_amount');

        $collectionRate = function (CarbonImmutable $from, CarbonImmutable $to): ?float {
            $totals = $this->inScope(Debt::query(), 'room_id')
                ->where('created_at', '>=', $from)->where('created_at', '<', $to)
                ->where('status', '!=', DebtStatus::Waived->value)
                ->selectRaw('COALESCE(SUM(paid_amount), 0) AS paid, COALESCE(SUM(remaining_amount), 0) AS remaining')
                ->first();
            $paid = (int) ($totals->paid ?? 0);
            $due = $paid + (int) ($totals->remaining ?? 0);

            return $due > 0 ? round($paid / $due * 100, 1) : null;
        };

        $highSecurity = fn(CarbonImmutable $from, CarbonImmutable $to): int => SecurityEvent::query()
            ->where('severity', self::SECURITY_SEVERITY_HIGH)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->count();

        $outstanding = $this->outstandingDebts()
            ->selectRaw('COALESCE(SUM(remaining_amount), 0) AS amount, COUNT(*) AS debts')
            ->first();

        $dayAgo = $now->subDay();

        return [
            'active_rooms' => ['value' => $activeRooms($start, $now), 'previous' => $activeRooms($previousStart, $start)],
            'active_users' => ['value' => $activeUsers($start, $now), 'previous' => $activeUsers($previousStart, $start)],
            'gmv' => ['value' => $gmv($start, $now), 'previous' => $gmv($previousStart, $start)],
            'collection_rate' => ['value' => $collectionRate($start, $now), 'previous' => $collectionRate($previousStart, $start)],
            'outstanding_debt' => ['value' => (int) ($outstanding->amount ?? 0), 'previous' => null, 'count' => (int) ($outstanding->debts ?? 0)],
            'high_security_events' => ['value' => $highSecurity($dayAgo, $now), 'previous' => $highSecurity($dayAgo->subDay(), $dayAgo)],
        ];
    }

    /**
     * Orders count and GMV per day over the period (zero-filled, oldest first).
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array{date: string, orders: int, gmv: int}> Daily points.
     */
    public function dailySeries(CarbonImmutable $now): array
    {
        $firstDay = $now->startOfDay()->subDays(self::PERIOD_DAYS - 1);

        $rows = $this->validOrders()
            ->where('created_at', '>=', $firstDay)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(final_amount), 0) AS gmv')
            ->groupBy('day')
            ->get()
            ->keyBy(fn($row): string => substr((string) $row->day, 0, 10));

        $series = [];
        for ($i = 0; $i < self::PERIOD_DAYS; $i++) {
            $date = $firstDay->addDays($i)->toDateString();
            $row = $rows->get($date);
            $series[] = ['date' => $date, 'orders' => (int) ($row->orders ?? 0), 'gmv' => (int) ($row->gmv ?? 0)];
        }

        return $series;
    }

    /**
     * Outstanding debt grouped by age (days since the debt was created).
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array{bucket: string, amount: int, count: int}> One entry per bucket in AGING_BUCKETS order.
     */
    public function debtAging(CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        $cases = [];
        $bindings = [];
        foreach (self::AGING_BUCKETS as $key => [$minDays, $maxDays]) {
            // Age in days = whole days between the debt's creation day and today.
            $condition = 'created_at < ?';
            $bindings[] = $today->subDays($minDays - 1);
            if ($maxDays !== null) {
                $condition .= ' AND created_at >= ?';
                $bindings[] = $today->subDays($maxDays);
            }
            $cases[] = "WHEN {$condition} THEN '{$key}'";
        }

        $rows = $this->outstandingDebts()
            ->selectRaw('CASE ' . implode(' ', $cases) . " ELSE '0_7' END AS bucket", $bindings)
            ->selectRaw('COALESCE(SUM(remaining_amount), 0) AS amount, COUNT(*) AS debts')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return array_map(static fn(string $key): array => [
            'bucket' => $key,
            'amount' => (int) ($rows->get($key)->amount ?? 0),
            'count' => (int) ($rows->get($key)->debts ?? 0),
        ], array_keys(self::AGING_BUCKETS));
    }

    /**
     * Health row for every non-archived room with warning flags.
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array<string, mixed>> Rooms sorted by number of flags, then outstanding debt.
     */
    public function roomHealth(CarbonImmutable $now): array
    {
        $start = $now->subDays(self::PERIOD_DAYS);
        $overdueBefore = $now->subDays(self::OVERDUE_DEBT_DAYS);
        $outstandingStatuses = $this->outstandingStatuses();
        $cancelled = OrderStatus::Cancelled->value;

        $rooms = $this->inScope(Room::query(), 'id')
            ->where('status', '!=', RoomStatus::Archived->value)
            ->select(['id', 'name', 'slug', 'status', 'created_at'])
            ->withCount([
                'roomUsers as members_total',
                'roomUsers as members_active' => fn(Builder $q): Builder => $q->where('status', RoomUserStatus::Active->value),
                'campaigns as campaigns_period' => fn(Builder $q): Builder => $q->where('created_at', '>=', $start),
                'campaigns as campaigns_cancelled' => fn(Builder $q): Builder => $q->where('created_at', '>=', $start)
                    ->where('status', CampaignStatus::Cancelled->value),
                'orders as orders_period' => fn(Builder $q): Builder => $q->where('created_at', '>=', $start)->where('status', '!=', $cancelled),
                'admins as active_admins' => fn(Builder $q): Builder => $q->where('admins.status', AdminStatus::Active->value),
            ])
            ->withSum(['orders as gmv_period' => fn(Builder $q): Builder => $q->where('created_at', '>=', $start)->where('status', '!=', $cancelled)], 'final_amount')
            ->withSum(['debts as debt_outstanding' => fn(Builder $q): Builder => $q->whereIn('status', $outstandingStatuses)], 'remaining_amount')
            ->withSum(['debts as debt_overdue' => fn(Builder $q): Builder => $q->whereIn('status', $outstandingStatuses)
                ->where('created_at', '<', $overdueBefore)], 'remaining_amount')
            ->withMax('campaigns as last_campaign_at', 'created_at')
            ->withMax('orders as last_order_at', 'created_at')
            ->get();

        $rows = $rooms->map(function (Room $room) use ($now): array {
            $outstanding = (int) $room->debt_outstanding;
            $overdue = (int) $room->debt_overdue;
            $campaigns = (int) $room->campaigns_period;
            $lastActivity = collect([$room->last_campaign_at, $room->last_order_at])
                ->filter()->map(fn($value): CarbonImmutable => CarbonImmutable::parse($value))->max();
            $dormantSince = $lastActivity ?? CarbonImmutable::parse($room->created_at);

            $flags = [];
            if ($dormantSince->lt($now->subDays(self::DORMANT_DAYS))) {
                $flags[] = self::FLAG_DORMANT;
            }
            if ($outstanding > 0 && $overdue / $outstanding > self::BAD_DEBT_RATIO) {
                $flags[] = self::FLAG_BAD_DEBT;
            }
            if ((int) $room->active_admins === 0) {
                $flags[] = self::FLAG_NO_ADMIN;
            }
            if ($campaigns >= self::HIGH_CANCEL_MIN_CAMPAIGNS && $room->campaigns_cancelled / $campaigns > self::HIGH_CANCEL_RATIO) {
                $flags[] = self::FLAG_HIGH_CANCEL;
            }

            return [
                'id' => $room->id,
                'name' => $room->name,
                'slug' => $room->slug,
                'status' => $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status,
                'members_active' => (int) $room->members_active,
                'members_total' => (int) $room->members_total,
                'campaigns' => $campaigns,
                'campaigns_cancelled' => (int) $room->campaigns_cancelled,
                'orders' => (int) $room->orders_period,
                'gmv' => (int) $room->gmv_period,
                'debt_outstanding' => $outstanding,
                'debt_overdue' => $overdue,
                'debt_overdue_ratio' => $outstanding > 0 ? round($overdue / $outstanding * 100, 1) : 0.0,
                'active_admins' => (int) $room->active_admins,
                'last_activity_at' => $lastActivity?->toIso8601String(),
                'flags' => $flags,
            ];
        });

        return $rows
            ->sortBy([
                fn(array $a, array $b): int => count($b['flags']) <=> count($a['flags']),
                fn(array $a, array $b): int => $b['debt_outstanding'] <=> $a['debt_outstanding'],
            ])
            ->values()
            ->all();
    }

    /**
     * Active/closing campaigns whose deadline passed more than STUCK_CAMPAIGN_GRACE_HOURS ago.
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array<string, mixed>> Stuck campaigns, oldest deadline first.
     */
    public function stuckCampaigns(CarbonImmutable $now): array
    {
        return $this->inScope(Campaign::query(), 'room_id')
            ->whereIn('status', [CampaignStatus::Active->value, CampaignStatus::Closing->value])
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now->subHours(self::STUCK_CAMPAIGN_GRACE_HOURS))
            ->with('room:id,name,slug')
            ->withCount(['orders' => fn(Builder $q): Builder => $q->where('status', '!=', OrderStatus::Cancelled->value)])
            ->orderBy('deadline')
            ->limit(self::STUCK_CAMPAIGN_LIMIT)
            ->get()
            ->map(fn(Campaign $campaign): array => [
                'id' => $campaign->id,
                'code' => $campaign->code,
                'name' => $campaign->name,
                'restaurant' => $campaign->restaurant,
                'status' => $campaign->status->value,
                'room' => $campaign->room?->name,
                'deadline' => $campaign->deadline?->toIso8601String(),
                'overdue_hours' => (int) floor($campaign->deadline->diffInMinutes($now) / 60),
                'orders' => (int) $campaign->orders_count,
            ])
            ->all();
    }

    /**
     * Orders that count toward activity and GMV (everything except cancelled).
     *
     * @return Builder<Order>
     */
    private function validOrders(): Builder
    {
        return $this->inScope(Order::query(), 'orders.room_id')->where('orders.status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Debts that still have money to collect.
     *
     * @return Builder<Debt>
     */
    private function outstandingDebts(): Builder
    {
        return $this->inScope(Debt::query(), 'room_id')->whereIn('status', $this->outstandingStatuses())->where('remaining_amount', '>', 0);
    }

    /**
     * @return list<string> Debt statuses that are not settled.
     */
    private function outstandingStatuses(): array
    {
        return [DebtStatus::Unpaid->value, DebtStatus::Pending->value, DebtStatus::Partial->value];
    }
}
