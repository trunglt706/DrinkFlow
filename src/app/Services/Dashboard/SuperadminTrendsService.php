<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\ContactTopic;
use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Models\ContactInquiry;
use App\Models\Feedback;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\SystemMetricSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Long-range superadmin analytics: product metrics (active members, campaign participation, debt payment time),
 * monthly signup cohorts and their ordering retention, feedback ratings, contact requests by topic, and
 * infrastructure history from system_metric_snapshots.
 *
 * Month and day buckets are computed in PHP in the application timezone so the queries stay portable
 * between MySQL and SQLite.
 */
class SuperadminTrendsService
{
    /** Signup cohorts shown (current month included). */
    public const COHORT_MONTHS = 6;

    /** Months of feedback ratings shown (current month included). */
    public const FEEDBACK_MONTHS = 6;

    /** Days of contact requests counted per topic. */
    public const CONTACT_DAYS = 90;

    /** Days of infrastructure snapshots returned. */
    public const HISTORY_DAYS = 7;

    public const RATINGS = [1, 2, 3, 4, 5];

    public const CACHE_TTL = 600;
    public const CACHE_KEY = 'superadmin.dashboard.trends';

    public function __construct(private readonly ProductMetricsService $product) {}

    /**
     * Build (or read from cache) the trends payload.
     *
     * @param bool $fresh Bypass and refresh the cache.
     * @return array<string, mixed> Payload with keys product (ProductMetricsService), cohorts, feedback, contact_topics,
     *                              system_history, generated_at.
     */
    public function trends(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn(): array => $this->compute(CarbonImmutable::now()));
    }

    /**
     * Compute the trends payload at a given moment.
     *
     * @param CarbonImmutable $now Reference time.
     * @return array<string, mixed> Trends payload.
     */
    public function compute(CarbonImmutable $now): array
    {
        return [
            'product' => $this->product->compute($now),
            'cohorts' => $this->cohorts($now),
            'feedback' => $this->feedback($now),
            'contact_topics' => $this->contactTopics($now),
            'system_history' => $this->systemHistory($now),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Monthly signup cohorts with the share of each cohort that placed a (non-cancelled) order
     * in the signup month (offset 0) and in each following month.
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array{month: string, size: int, retention: list<array{offset: int, active: int, rate: float}>}> Oldest cohort first.
     */
    public function cohorts(CarbonImmutable $now): array
    {
        $firstMonth = $now->startOfMonth()->subMonths(self::COHORT_MONTHS - 1);
        $monthIndex = static fn(CarbonImmutable $date): int => ($date->year - $firstMonth->year) * 12 + $date->month - $firstMonth->month;

        /** @var array<int, int> $cohortOf global user id => cohort index */
        $cohortOf = [];
        $sizes = array_fill(0, self::COHORT_MONTHS, 0);
        GlobalUser::query()
            ->where('status', '!=', GlobalUserStatus::Deleted->value)
            ->where('created_at', '>=', $firstMonth)
            ->select(['id', 'created_at'])
            ->lazyById(2000)
            ->each(function (GlobalUser $user) use (&$cohortOf, &$sizes, $monthIndex): void {
                $index = $monthIndex(CarbonImmutable::parse($user->created_at));
                if ($index >= 0 && $index < self::COHORT_MONTHS) {
                    $cohortOf[$user->id] = $index;
                    $sizes[$index]++;
                }
            });

        /** @var array<int, array<int, true>> $activeAt cohort index => offset => [user id => true] */
        $activeAt = [];
        if ($cohortOf !== []) {
            Order::query()
                ->join('room_users', 'room_users.id', '=', 'orders.room_user_id')
                ->join('global_users', 'global_users.id', '=', 'room_users.global_user_id')
                ->where('global_users.created_at', '>=', $firstMonth)
                ->where('orders.created_at', '>=', $firstMonth)
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->select(['room_users.global_user_id', 'orders.created_at'])
                ->toBase()
                ->cursor()
                ->each(function (object $row) use ($cohortOf, &$activeAt, $monthIndex): void {
                    $userId = (int) $row->global_user_id;
                    if (! isset($cohortOf[$userId])) {
                        return;
                    }
                    $cohort = $cohortOf[$userId];
                    $offset = $monthIndex(CarbonImmutable::parse($row->created_at)) - $cohort;
                    if ($offset >= 0) {
                        $activeAt[$cohort][$offset][$userId] = true;
                    }
                });
        }

        $cohorts = [];
        for ($index = 0; $index < self::COHORT_MONTHS; $index++) {
            $retention = [];
            // Only offsets that have (at least partly) happened: the newest cohort only has offset 0.
            for ($offset = 0; $offset < self::COHORT_MONTHS - $index; $offset++) {
                $active = count($activeAt[$index][$offset] ?? []);
                $retention[] = [
                    'offset' => $offset,
                    'active' => $active,
                    'rate' => $sizes[$index] > 0 ? round($active / $sizes[$index] * 100, 1) : 0.0,
                ];
            }
            $cohorts[] = [
                'month' => $firstMonth->addMonths($index)->format('Y-m'),
                'size' => $sizes[$index],
                'retention' => $retention,
            ];
        }

        return $cohorts;
    }

    /**
     * Feedback ratings: monthly average and count, plus the 1–5 distribution over the whole window.
     *
     * @param CarbonImmutable $now Reference time.
     * @return array{monthly: list<array{month: string, count: int, average: float|null}>, distribution: array<int, int>, count: int, average: float|null}
     */
    public function feedback(CarbonImmutable $now): array
    {
        $firstMonth = $now->startOfMonth()->subMonths(self::FEEDBACK_MONTHS - 1);
        $monthly = [];
        for ($i = 0; $i < self::FEEDBACK_MONTHS; $i++) {
            $monthly[$firstMonth->addMonths($i)->format('Y-m')] = ['sum' => 0, 'count' => 0];
        }
        $distribution = array_fill_keys(self::RATINGS, 0);

        Feedback::query()
            ->where('created_at', '>=', $firstMonth)
            ->whereBetween('rating', [min(self::RATINGS), max(self::RATINGS)])
            ->select(['id', 'rating', 'created_at'])
            ->lazyById(2000)
            ->each(function (Feedback $feedback) use (&$monthly, &$distribution): void {
                $month = CarbonImmutable::parse($feedback->created_at)->format('Y-m');
                if (isset($monthly[$month])) {
                    $monthly[$month]['sum'] += $feedback->rating;
                    $monthly[$month]['count']++;
                }
                $distribution[$feedback->rating]++;
            });

        $total = array_sum($distribution);
        $weighted = array_sum(array_map(static fn(int $rating, int $count): int => $rating * $count, array_keys($distribution), $distribution));

        return [
            'monthly' => array_map(static fn(string $month, array $bucket): array => [
                'month' => $month,
                'count' => $bucket['count'],
                'average' => $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 2) : null,
            ], array_keys($monthly), $monthly),
            'distribution' => $distribution,
            'count' => $total,
            'average' => $total > 0 ? round($weighted / $total, 2) : null,
        ];
    }

    /**
     * Contact requests per topic over CONTACT_DAYS (every topic listed, most frequent first).
     *
     * @param CarbonImmutable $now Reference time.
     * @return list<array{topic: string, count: int}> Topic counts.
     */
    public function contactTopics(CarbonImmutable $now): array
    {
        $counts = ContactInquiry::query()
            ->where('created_at', '>=', $now->subDays(self::CONTACT_DAYS))
            ->selectRaw('topic, COUNT(*) AS total')
            ->groupBy('topic')
            ->pluck('total', 'topic');

        $topics = array_map(static fn(ContactTopic $topic): array => [
            'topic' => $topic->value,
            'count' => (int) ($counts[$topic->value] ?? 0),
        ], ContactTopic::cases());
        usort($topics, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return $topics;
    }

    /**
     * Infrastructure snapshots over HISTORY_DAYS with uptime ratios and a storage-full forecast.
     *
     * @param CarbonImmutable $now Reference time.
     * @return array{points: list<array<string, mixed>>, database_uptime: float|null, socket_uptime: float|null, storage_days_left: int|null}
     */
    public function systemHistory(CarbonImmutable $now): array
    {
        $snapshots = SystemMetricSnapshot::query()
            ->where('captured_at', '>=', $now->subDays(self::HISTORY_DAYS))
            ->orderBy('captured_at')
            ->get();

        $ratio = static function (array $values): ?float {
            $known = array_filter($values, static fn(?bool $value): bool => $value !== null);

            return $known === [] ? null : round(count(array_filter($known)) / count($known) * 100, 1);
        };

        return [
            'points' => $snapshots->map(fn(SystemMetricSnapshot $snapshot): array => [
                'at' => $snapshot->captured_at->toIso8601String(),
                'pending_jobs' => $snapshot->pending_jobs,
                'failed_jobs' => $snapshot->failed_jobs,
                'storage_used_bytes' => $snapshot->storage_used_bytes,
                'storage_total_bytes' => $snapshot->storage_total_bytes,
                'socket_connections' => $snapshot->socket_connections,
            ])->all(),
            'database_uptime' => $ratio($snapshots->pluck('database_ok')->all()),
            'socket_uptime' => $ratio($snapshots->pluck('socket_ok')->all()),
            'storage_days_left' => $this->storageDaysLeft($snapshots->all()),
        ];
    }

    /**
     * Days until storage is full, extrapolating the least-squares growth rate over the snapshots.
     *
     * @param list<SystemMetricSnapshot> $snapshots Snapshots ordered by capture time.
     * @return int|null Null when usage is flat/shrinking, unknown, or there are too few readings.
     */
    private function storageDaysLeft(array $snapshots): ?int
    {
        $readings = array_values(array_filter($snapshots, static fn(SystemMetricSnapshot $snapshot): bool => $snapshot->storage_used_bytes !== null
            && $snapshot->storage_total_bytes !== null && $snapshot->storage_total_bytes > 0));
        if (count($readings) < 2) {
            return null;
        }

        $origin = $readings[0]->captured_at->getTimestamp();
        $xs = array_map(static fn(SystemMetricSnapshot $snapshot): float => ($snapshot->captured_at->getTimestamp() - $origin) / 86400, $readings);
        $ys = array_map(static fn(SystemMetricSnapshot $snapshot): float => (float) $snapshot->storage_used_bytes, $readings);
        $n = count($xs);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;
        $denominator = array_sum(array_map(static fn(float $x): float => ($x - $meanX) ** 2, $xs));
        if ($denominator <= 0.0) {
            return null;
        }
        $slope = array_sum(array_map(static fn(float $x, float $y): float => ($x - $meanX) * ($y - $meanY), $xs, $ys)) / $denominator;
        if ($slope <= 0.0) {
            return null;
        }

        $latest = end($readings);
        $free = max(0, $latest->storage_total_bytes - $latest->storage_used_bytes);

        return (int) floor($free / $slope);
    }
}
