<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\MonthlySponsorStatistic;
use App\Models\Room;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonthlySponsorStatisticsService
{
    /** Seconds the achievements of a member are cached (the statistics only change when a month is rebuilt). */
    private const ACHIEVEMENTS_CACHE_SECONDS = 86400;

    public function __construct(private readonly SponsorLeaderboardService $leaderboard) {}

    /**
     * Replace a room/month snapshot with current totals for every real sponsor membership.
     * Uses campaign creation month and completed orders, matching the room leaderboard.
     * A room row lock serializes concurrent rebuilds; stale sponsors are removed atomically.
     *
     * @return int Number of sponsors with a positive contribution.
     */
    public function rebuild(Room $room, CarbonImmutable $month): int
    {
        $month = $month->startOfMonth();

        $count = DB::transaction(function () use ($room, $month): int {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $totals = $this->leaderboard->build(
                $room, $month->toMutable(), $month->endOfMonth()->toMutable(), null,
                [OrderStatus::Completed],
                [CampaignStatus::Active, CampaignStatus::Closing, CampaignStatus::Closed, CampaignStatus::Archived],
            )->filter(static fn (array $row): bool => $row['room_user_id'] !== null && $row['total_sponsored'] > 0);

            // Ignore stale/deleted memberships and invalid cross-room allocations.
            $memberIds = RoomUser::query()->where('room_id', $room->id)
                ->whereIn('id', $totals->pluck('room_user_id'))->pluck('id')->flip();
            $totals = $totals->filter(static fn (array $row): bool => $memberIds->has($row['room_user_id']));
            $timestamp = now();
            $rows = $totals->map(static fn (array $row): array => [
                'room_id' => $room->id,
                'room_user_id' => $row['room_user_id'],
                'month' => $month->toDateString(),
                'total_sponsored' => $row['total_sponsored'],
                'sponsored_campaigns' => $row['sponsored_campaigns'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            foreach ($rows->chunk(500) as $chunk) {
                MonthlySponsorStatistic::upsert($chunk->all(), ['room_id', 'month', 'room_user_id'], [
                    'total_sponsored', 'sponsored_campaigns', 'updated_at',
                ]);
            }

            MonthlySponsorStatistic::query()->where('room_id', $room->id)
                ->where('month', $month->toDateString())
                ->whereNotIn('room_user_id', $totals->pluck('room_user_id'))
                ->delete();

            return $rows->count();
        }, 3);
        // New statistics version: the cached achievements of the room are recomputed on their next read.
        Cache::forever(self::statisticsVersionKey($room), (string) Str::uuid());

        return $count;
    }

    /**
     * Rebuild the snapshot shown on the dashboards (the preceding calendar month) for one room.
     *
     * @return int Number of sponsors with a positive contribution.
     */
    public function rebuildPreviousMonth(Room $room): int
    {
        return $this->rebuild($room, $this->previousMonth());
    }

    /**
     * Read only the preceding calendar month's persisted podium, scoped to the current room.
     * Equal totals are ordered by membership ID so medals remain stable between requests.
     *
     * @return array{month: string, sponsors: list<array{name: string, amount: int, campaigns: int}>}
     */
    public function previousMonthHonors(Room $room): array
    {
        $month = $this->previousMonth();
        $sponsors = $this->podiumQuery($room, $month)
            ->with('roomUser.globalUser')
            ->get()
            ->map(static fn (MonthlySponsorStatistic $statistic): array => [
                'name' => $statistic->roomUser?->payerName() ?: __('sponsor_honors.anonymous'),
                'amount' => $statistic->total_sponsored,
                'campaigns' => $statistic->sponsored_campaigns,
            ])->all();

        return ['month' => $month->format('m/Y'), 'sponsors' => $sponsors];
    }

    /**
     * Medal rank (1 = gold … 3 = bronze) of each previous-month podium member, for name badges.
     * Uses the same ordering as {@see previousMonthHonors()} so badges always match the podium.
     *
     * @return array{month: string, ranks: array<int, int>} Ranks keyed by room_user_id.
     */
    public function previousMonthRanks(Room $room): array
    {
        $month = $this->previousMonth();
        $ranks = [];
        foreach ($this->podiumQuery($room, $month)->pluck('room_user_id')->values() as $index => $roomUserId) {
            $ranks[(int) $roomUserId] = $index + 1;
        }

        return ['month' => $month->format('m/Y'), 'ranks' => $ranks];
    }

    /**
     * Top-3 medals one member earned in the room over recent completed months, newest first.
     * A month's rank uses the podium ordering (total desc, then membership ID), so it matches the honors block.
     *
     * @param int $months How many completed months (before the current one) to look back.
     * @return list<array{rank: int, month: string}> Earned medals; month formatted m/Y.
     */
    public function recentBadges(Room $room, RoomUser $roomUser, int $months = 12): array
    {
        $latest = $this->previousMonth();
        $statistics = MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('room_user_id', $roomUser->id)
            ->where('total_sponsored', '>', 0)
            ->whereBetween('month', [$latest->subMonths(max(0, $months - 1))->toDateString(), $latest->toDateString()])
            ->orderByDesc('month')
            ->get(['month', 'total_sponsored']);

        $badges = [];
        foreach ($statistics as $statistic) {
            $month = CarbonImmutable::parse($statistic->getRawOriginal('month'));
            $ahead = MonthlySponsorStatistic::query()
                ->where('room_id', $room->id)->where('month', $month->toDateString())
                ->where(static fn ($query) => $query->where('total_sponsored', '>', $statistic->total_sponsored)
                    ->orWhere(static fn ($tie) => $tie->where('total_sponsored', $statistic->total_sponsored)
                        ->where('room_user_id', '<', $roomUser->id)))
                ->count();
            if ($ahead < 3) {
                $badges[] = ['rank' => $ahead + 1, 'month' => $month->format('m/Y')];
            }
        }

        return $badges;
    }

    /**
     * Admin report of the medals awarded in one year: each completed month's podium and,
     * per member, how many gold/silver/bronze badges they earned that year.
     *
     * @return array{year: int, years: list<int>, months: list<array{month: string, podium: list<array{rank: int, name: string, amount: int}>}>, members: list<array{name: string, gold: int, silver: int, bronze: int, total: int}>}
     */
    public function yearlyBadgeReport(Room $room, int $year): array
    {
        $current = CarbonImmutable::now()->startOfMonth();
        $statistics = MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('total_sponsored', '>', 0)
            ->whereBetween('month', [sprintf('%04d-01-01', $year), sprintf('%04d-12-01', $year)])
            ->where('month', '<', $current->toDateString())
            ->with('roomUser.globalUser')
            ->orderByDesc('month')->orderByDesc('total_sponsored')->orderBy('room_user_id')
            ->get();

        $months = [];
        $members = [];
        foreach ($statistics->groupBy(static fn (MonthlySponsorStatistic $row): string => substr((string) $row->getRawOriginal('month'), 0, 10)) as $month => $rows) {
            $podium = [];
            foreach ($rows->take(3)->values() as $index => $row) {
                $name = $row->roomUser?->payerName() ?: __('sponsor_honors.anonymous');
                $podium[] = ['rank' => $index + 1, 'name' => $name, 'amount' => $row->total_sponsored];
                $members[$row->room_user_id] ??= ['name' => $name, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'total' => 0];
                $members[$row->room_user_id][['gold', 'silver', 'bronze'][$index]]++;
                $members[$row->room_user_id]['total']++;
            }
            $months[] = ['month' => CarbonImmutable::parse($month)->format('m/Y'), 'podium' => $podium];
        }

        $members = collect($members)
            ->sortBy([['total', 'desc'], ['gold', 'desc'], ['silver', 'desc'], ['bronze', 'desc'], ['name', 'asc']])
            ->values()->all();
        $years = MonthlySponsorStatistic::query()->where('room_id', $room->id)->distinct()->pluck('month')
            ->map(static fn ($month): int => (int) substr((string) $month, 0, 4))
            ->push((int) $current->subMonth()->format('Y'))->push($year)
            ->unique()->sortDesc()->values()->all();

        return ['year' => $year, 'years' => $years, 'months' => $months, 'members' => $members];
    }

    /**
     * Achievements of one member in one year (room "Achievements" page): every month with its medal (TOP 1-3 of
     * the month's sponsors), the member's sponsored amount and position, and the medal totals of the year.
     * Positions use the podium ordering (total desc, then membership ID), so medals match the honors block and the
     * admin report. Only completed months are ranked (the current month is computed after it ends).
     *
     * @param Room $room Room.
     * @param RoomUser $roomUser Member.
     * @param int $year Calendar year.
     * @return array{year: int, months: list<array{month: int, status: string, rank: int|null, position: int|null, amount: int}>, totals: array{gold: int, silver: int, bronze: int, total: int}}
     *         `status`: medal, none (completed month without a medal), current, upcoming.
     */
    public function memberYearAchievements(Room $room, RoomUser $roomUser, int $year): array
    {
        // Cached for a day; the key changes with the month and whenever the room statistics are rebuilt.
        $current = CarbonImmutable::now()->startOfMonth();
        $version = (string) Cache::get(self::statisticsVersionKey($room), '0');

        return Cache::remember(
            "room:{$room->id}:achievements:{$roomUser->id}:{$year}:{$current->format('Y-m')}:{$version}",
            self::ACHIEVEMENTS_CACHE_SECONDS,
            fn (): array => $this->computeMemberYearAchievements($room, $roomUser, $year, $current),
        );
    }

    /**
     * @return array{year: int, months: list<array{month: int, status: string, rank: int|null, position: int|null, amount: int}>, totals: array{gold: int, silver: int, bronze: int, total: int}}
     */
    private function computeMemberYearAchievements(Room $room, RoomUser $roomUser, int $year, CarbonImmutable $current): array
    {
        $ranked = MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('total_sponsored', '>', 0)
            ->whereBetween('month', [sprintf('%04d-01-01', $year), sprintf('%04d-12-01', $year)])
            ->where('month', '<', $current->toDateString())
            ->orderBy('month')->orderByDesc('total_sponsored')->orderBy('room_user_id')
            ->get(['month', 'room_user_id', 'total_sponsored'])
            ->groupBy(static fn (MonthlySponsorStatistic $row): int => (int) substr((string) $row->getRawOriginal('month'), 5, 2));

        $months = [];
        $totals = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total' => 0];
        for ($month = 1; $month <= 12; $month++) {
            $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $entry = ['month' => $month, 'status' => 'none', 'rank' => null, 'position' => null, 'amount' => 0];
            if ($start->greaterThan($current)) {
                $entry['status'] = 'upcoming';
            } elseif ($start->equalTo($current)) {
                $entry['status'] = 'current';
            } else {
                $rows = ($ranked->get($month) ?? collect())->values();
                $index = $rows->search(static fn (MonthlySponsorStatistic $row): bool => (int) $row->room_user_id === $roomUser->id);
                if ($index !== false) {
                    $entry['position'] = $index + 1;
                    $entry['amount'] = (int) $rows[$index]->total_sponsored;
                    if ($index < 3) {
                        $entry['status'] = 'medal';
                        $entry['rank'] = $index + 1;
                        $totals[['gold', 'silver', 'bronze'][$index]]++;
                        $totals['total']++;
                    }
                }
            }
            $months[] = $entry;
        }

        return ['year' => $year, 'months' => $months, 'totals' => $totals];
    }

    /**
     * Top three positive sponsors of one room/month; equal totals ordered by membership ID.
     *
     * @return \Illuminate\Database\Eloquent\Builder<MonthlySponsorStatistic>
     */
    private function podiumQuery(Room $room, CarbonImmutable $month): \Illuminate\Database\Eloquent\Builder
    {
        return MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('month', $month->toDateString())
            ->where('total_sponsored', '>', 0)
            ->orderByDesc('total_sponsored')->orderBy('room_user_id')->limit(3);
    }

    /**
     * First day of the calendar month the dashboards honor.
     */
    private function previousMonth(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth()->subMonth();
    }

    /**
     * Cache key of the statistics version of a room (changes on every rebuild).
     */
    private static function statisticsVersionKey(Room $room): string
    {
        return "room:{$room->id}:sponsor-statistics:version";
    }
}
