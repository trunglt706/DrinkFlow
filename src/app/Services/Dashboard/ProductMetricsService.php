<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Product metrics of the platform (superadmin trends): active members per day / week / month from the room page
 * visits, stickiness, participation in the campaigns closed recently and how long debts take to be paid.
 *
 * Days are counted in the application timezone; buckets are built in PHP so the queries stay portable between
 * MySQL and SQLite.
 */
class ProductMetricsService
{
    /** Days of the DAU series and of MAU, days of WAU, days of closed campaigns, days of paid debts. */
    public const ACTIVITY_DAYS = 30;

    public const WEEK_DAYS = 7;

    public const CAMPAIGN_DAYS = 30;

    public const DEBT_DAYS = 90;

    /**
     * Record that a member opened a room page today (at most one row per day, room and account).
     *
     * @param RoomUser $member Member of the room page.
     */
    public function recordActivity(RoomUser $member): void
    {
        DB::table('daily_active_members')->insertOrIgnore([
            'activity_date' => CarbonImmutable::now()->toDateString(),
            'room_id' => $member->room_id,
            'global_user_id' => $member->global_user_id,
        ]);
    }

    /**
     * @param CarbonImmutable $now Reference time.
     * @return array{dau: int, wau: int, mau: int, stickiness: float|null, daily: list<array{date: string, users: int}>,
     *     participation: array{campaigns: int, rate: float|null}, debt_payment: array{debts: int, average_hours: float|null, median_hours: float|null}}
     */
    public function compute(CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        $from = $today->subDays(self::ACTIVITY_DAYS - 1);
        $perDay = DB::table('daily_active_members')
            ->where('activity_date', '>=', $from->toDateString())
            ->where('activity_date', '<=', $today->toDateString())
            ->groupBy('activity_date')
            ->select(['activity_date', DB::raw('COUNT(DISTINCT global_user_id) as users')])
            ->pluck('users', 'activity_date')
            ->mapWithKeys(static fn (mixed $users, string $date): array => [substr($date, 0, 10) => (int) $users]);

        $daily = [];
        for ($day = $from; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $daily[] = ['date' => $day->toDateString(), 'users' => (int) ($perDay[$day->toDateString()] ?? 0)];
        }
        $mau = $this->distinctUsers($from, $today);
        $averageDau = array_sum(array_column($daily, 'users')) / self::ACTIVITY_DAYS;

        return [
            'dau' => (int) ($perDay[$today->toDateString()] ?? 0),
            'wau' => $this->distinctUsers($today->subDays(self::WEEK_DAYS - 1), $today),
            'mau' => $mau,
            'stickiness' => $mau > 0 ? round($averageDau / $mau * 100, 1) : null,
            'daily' => $daily,
            'participation' => $this->participation($now),
            'debt_payment' => $this->debtPayment($now),
        ];
    }

    private function distinctUsers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('daily_active_members')
            ->where('activity_date', '>=', $from->toDateString())
            ->where('activity_date', '<=', $to->toDateString())
            ->distinct()
            ->count('global_user_id');
    }

    /**
     * Share of the room members who ordered in the campaigns closed during the last CAMPAIGN_DAYS days (all campaigns
     * together: ordering members / active members of each campaign's room).
     *
     * @return array{campaigns: int, rate: float|null}
     */
    private function participation(CarbonImmutable $now): array
    {
        $campaigns = Campaign::query()
            ->whereIn('status', [CampaignStatus::Closed->value, CampaignStatus::Archived->value])
            ->where('closed_at', '>=', $now->subDays(self::CAMPAIGN_DAYS))
            ->get(['id', 'room_id']);
        if ($campaigns->isEmpty()) {
            return ['campaigns' => 0, 'rate' => null];
        }

        $participants = Order::query()
            ->whereIn('campaign_id', $campaigns->modelKeys())
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('campaign_id')
            ->select(['campaign_id', DB::raw('COUNT(DISTINCT room_user_id) as members')])
            ->pluck('members', 'campaign_id');
        $roomMembers = RoomUser::query()
            ->whereIn('room_id', $campaigns->pluck('room_id')->unique())
            ->where('status', RoomUserStatus::Active->value)
            ->groupBy('room_id')
            ->select(['room_id', DB::raw('COUNT(*) as members')])
            ->pluck('members', 'room_id');

        $ordered = 0;
        $eligible = 0;
        foreach ($campaigns as $campaign) {
            $ordered += (int) ($participants[$campaign->id] ?? 0);
            $eligible += (int) ($roomMembers[$campaign->room_id] ?? 0);
        }

        return ['campaigns' => $campaigns->count(), 'rate' => $eligible > 0 ? round(min(100, $ordered / $eligible * 100), 1) : null];
    }

    /**
     * Time from a debt's creation to its last payment, for the debts paid during the last DEBT_DAYS days.
     *
     * @return array{debts: int, average_hours: float|null, median_hours: float|null}
     */
    private function debtPayment(CarbonImmutable $now): array
    {
        $hours = Debt::query()
            ->where('status', DebtStatus::Paid->value)
            ->withMax('payments as last_paid_at', 'paid_at')
            ->whereHas('payments', static fn ($payments) => $payments->where('paid_at', '>=', $now->subDays(self::DEBT_DAYS)))
            ->get(['id', 'created_at'])
            ->map(static fn (Debt $debt): ?float => $debt->last_paid_at === null || $debt->created_at === null
                ? null
                : max(0, CarbonImmutable::parse($debt->last_paid_at)->diffInMinutes($debt->created_at, true)) / 60)
            ->filter(static fn (?float $value): bool => $value !== null)
            ->sort()
            ->values();
        if ($hours->isEmpty()) {
            return ['debts' => 0, 'average_hours' => null, 'median_hours' => null];
        }

        return [
            'debts' => $hours->count(),
            'average_hours' => round((float) $hours->avg(), 1),
            'median_hours' => round((float) $hours->median(), 1),
        ];
    }
}
