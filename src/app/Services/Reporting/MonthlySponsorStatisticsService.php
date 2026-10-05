<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\MonthlySponsorStatistic;
use App\Models\Room;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlySponsorStatisticsService
{
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

        return DB::transaction(function () use ($room, $month): int {
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
    }

    /**
     * Read only the preceding calendar month's persisted podium, scoped to the current room.
     * Equal totals are ordered by membership ID so medals remain stable between requests.
     *
     * @return array{month: string, sponsors: list<array{name: string, amount: int, campaigns: int}>}
     */
    public function previousMonthHonors(Room $room): array
    {
        $month = CarbonImmutable::now()->startOfMonth()->subMonth();
        $sponsors = MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('month', $month->toDateString())
            ->where('total_sponsored', '>', 0)
            ->with('roomUser.globalUser')
            ->orderByDesc('total_sponsored')->orderBy('room_user_id')->limit(3)->get()
            ->map(static fn (MonthlySponsorStatistic $statistic): array => [
                'name' => $statistic->roomUser?->payerName() ?: __('sponsor_honors.anonymous'),
                'amount' => $statistic->total_sponsored,
                'campaigns' => $statistic->sponsored_campaigns,
            ])->all();

        return ['month' => $month->format('m/Y'), 'sponsors' => $sponsors];
    }
}
