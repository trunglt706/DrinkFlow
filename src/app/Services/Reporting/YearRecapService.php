<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Models\CampaignItem;
use App\Models\MemberYearRecap;
use App\Models\MonthlySponsorStatistic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Year recap of a room member ("Wrapped"): cups, favourite dish and shop, busiest month, spending, sponsorship given
 * and received, medals, rank in the room. Recaps are computed in advance (drinkflow:build-year-recaps, every day of
 * the recap season) and stored in member_year_recaps; a missing or outdated row is computed on the first read.
 *
 * The recap season runs from 15 December to 31 January (room timezone): the year being recapped is the current
 * year in December and the previous one in January. Earlier years stay readable at any time.
 */
class YearRecapService
{
    /** Day of December the recap of the year opens, and the last day of January it is promoted. */
    public const SEASON_START_DAY = 15;

    public const SEASON_END_DAY = 31;

    /** Hours a recap of the year still in progress is reused before being recomputed. */
    public const LIVE_REFRESH_HOURS = 6;

    /** Cups from which a member gets each title (highest first); sponsors of 3+ campaigns are "patrons". */
    public const TITLES = ['legend' => 100, 'champion' => 50, 'fan' => 20, 'connoisseur' => 0];

    public const PATRON_CAMPAIGNS = 3;

    public function __construct(private readonly MonthlySponsorStatisticsService $statistics) {}

    /**
     * Year whose recap is in season for the room today, null outside the season.
     */
    public function seasonYear(Room $room): ?int
    {
        $today = $this->today($room);
        if ($today->month === 12 && $today->day >= self::SEASON_START_DAY) {
            return $today->year;
        }
        if ($today->month === 1 && $today->day <= self::SEASON_END_DAY) {
            return $today->year - 1;
        }

        return null;
    }

    /**
     * Whether the recap of a year can be opened: any past year, the current year once its season started.
     */
    public function isAvailable(Room $room, int $year): bool
    {
        $current = $this->today($room)->year;

        return $year >= 2000 && ($year < $current || ($year === $current && $this->seasonYear($room) === $current));
    }

    /**
     * Most recent year whose recap can be opened: the season year, otherwise last year.
     */
    public function latestYear(Room $room): int
    {
        return $this->seasonYear($room) ?? $this->today($room)->year - 1;
    }

    /**
     * Year whose recap the room dashboard promotes to the member: the season year, when they ordered that year.
     */
    public function promotedYear(Room $room, RoomUser $member): ?int
    {
        $year = $this->seasonYear($room);

        return $year !== null && $this->yearOrders($room, $year)->where('room_user_id', $member->id)->exists() ? $year : null;
    }

    /**
     * Recap of a member for a year: the stored one, computed (and stored) when missing, or when the year is still in
     * progress and the stored one is older than LIVE_REFRESH_HOURS. Null when the member ordered nothing that year.
     *
     * @return array<string, mixed>|null See compute().
     */
    public function forMember(Room $room, RoomUser $member, int $year): ?array
    {
        $stored = MemberYearRecap::query()->where('room_id', $room->id)->where('room_user_id', $member->id)->where('year', $year)->first();
        $inProgress = $year >= $this->today($room)->year;
        if ($stored !== null && (! $inProgress || $stored->updated_at?->greaterThan(now()->subHours(self::LIVE_REFRESH_HOURS)))) {
            return $stored->data;
        }

        $data = $this->compute($room, $member, $year, $this->roomCups($room, $year));
        $this->store($room, $member, $year, $data);

        return $data;
    }

    /**
     * Compute and store the recaps of every member of a room who ordered during the year.
     *
     * @return int Recaps stored.
     */
    public function rebuildRoom(Room $room, int $year): int
    {
        $cups = $this->roomCups($room, $year);
        $members = RoomUser::query()->where('room_id', $room->id)->whereIn('id', $cups->keys())->get();
        foreach ($members as $member) {
            $data = $this->compute($room, $member, $year, $cups);
            if ($data !== null) {
                $this->store($room, $member, $year, $data);
            }
        }

        return $members->count();
    }

    /**
     * Rebuild the recaps of the season year in every active room (none outside the season).
     *
     * @return int Recaps stored.
     */
    public function rebuildSeason(): int
    {
        $count = 0;
        Room::query()->where('status', RoomStatus::Active->value)->orderBy('id')->each(function (Room $room) use (&$count): void {
            $year = $this->seasonYear($room);
            if ($year !== null) {
                $count += $this->rebuildRoom($room, $year);
            }
        });

        return $count;
    }

    /**
     * Recap figures of a member.
     *
     * @param Collection<int, int> $roomCups Cups of each member of the room that year, keyed by member id.
     * @return array{year: int, orders: int, campaigns: int, cups: int, favorite_item: array{name: string, cups: int}|null,
     *     favorite_shop: array{name: string, orders: int}|null, busiest_month: array{month: int, cups: int}|null,
     *     first_order: string|null, spent: int, sponsored_received: int, sponsored_given: int, sponsored_campaigns: int,
     *     proxy_orders: int, medals: array{gold: int, silver: int, bronze: int, total: int}, rank: int, ranked_members: int,
     *     room_cups: int, title: string, patron: bool}|null Null when the member ordered nothing that year.
     */
    public function compute(Room $room, RoomUser $member, int $year, Collection $roomCups): ?array
    {
        $timezone = $this->timezone($room);
        $orders = $this->yearOrders($room, $year)
            ->where('room_user_id', $member->id)
            ->with('campaign:id,restaurant')
            ->withSum('items as cups', 'quantity')
            ->orderBy('created_at')
            ->get(['id', 'campaign_id', 'final_amount', 'sponsor_amount', 'created_at']);
        if ($orders->isEmpty()) {
            return null;
        }

        $cups = (int) $orders->sum('cups');
        $byMonth = $orders->groupBy(static fn (Order $order): int => (int) $order->created_at->copy()->timezone($timezone)->month)
            ->map(static fn (Collection $monthOrders): int => (int) $monthOrders->sum('cups'));
        $busiestMonth = $byMonth->sortDesc()->keys()->first();
        $shops = $orders->groupBy(static fn (Order $order): string => trim((string) $order->campaign?->restaurant))
            ->forget('')->map->count()->sortDesc();
        $sponsor = MonthlySponsorStatistic::query()
            ->where('room_id', $room->id)->where('room_user_id', $member->id)
            ->whereBetween('month', [sprintf('%04d-01-01', $year), sprintf('%04d-12-01', $year)])
            ->selectRaw('COALESCE(SUM(total_sponsored), 0) as amount, COALESCE(SUM(sponsored_campaigns), 0) as campaigns')
            ->first();
        $sponsoredCampaigns = (int) ($sponsor->campaigns ?? 0);
        $rank = 1 + $roomCups->filter(static fn (int $value): bool => $value > $cups)->count();

        return [
            'year' => $year,
            'orders' => $orders->count(),
            'campaigns' => $orders->pluck('campaign_id')->unique()->count(),
            'cups' => $cups,
            'favorite_item' => $this->favoriteItem($orders->modelKeys()),
            'favorite_shop' => $shops->isEmpty() ? null : ['name' => (string) $shops->keys()->first(), 'orders' => (int) $shops->first()],
            'busiest_month' => $busiestMonth === null ? null : ['month' => (int) $busiestMonth, 'cups' => (int) $byMonth[$busiestMonth]],
            'first_order' => $orders->first()->created_at->copy()->timezone($timezone)->format('d/m'),
            'spent' => (int) $orders->sum('final_amount'),
            'sponsored_received' => (int) $orders->sum('sponsor_amount'),
            'sponsored_given' => (int) ($sponsor->amount ?? 0),
            'sponsored_campaigns' => $sponsoredCampaigns,
            'proxy_orders' => $this->yearOrders($room, $year)
                ->where('room_user_id', '!=', $member->id)
                ->whereHas('parent', static fn (Builder $parent): Builder => $parent->where('room_user_id', $member->id))
                ->count(),
            'medals' => $this->statistics->memberYearAchievements($room, $member, $year)['totals'],
            'rank' => $rank,
            'ranked_members' => $roomCups->filter(static fn (int $value): bool => $value > 0)->count(),
            'room_cups' => (int) $roomCups->sum(),
            'title' => $this->title($cups),
            'patron' => $sponsoredCampaigns >= self::PATRON_CAMPAIGNS,
        ];
    }

    /**
     * Cups ordered by each member of the room during the year, keyed by member id.
     *
     * @return Collection<int, int>
     */
    public function roomCups(Room $room, int $year): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.order_id', $this->yearOrders($room, $year)->select('orders.id'))
            ->groupBy('orders.room_user_id')
            ->select(['orders.room_user_id', DB::raw('SUM(order_items.quantity) as cups')])
            ->pluck('cups', 'room_user_id')
            ->map(static fn (mixed $cups): int => (int) $cups);
    }

    /**
     * Non-cancelled orders of the room created during the year (room timezone), in campaigns that were not cancelled.
     *
     * @return Builder<Order>
     */
    private function yearOrders(Room $room, int $year): Builder
    {
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $this->timezone($room));

        return Order::query()
            ->where('orders.room_id', $room->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', $start->utc())
            ->where('orders.created_at', '<', $start->addYear()->utc())
            ->whereHas('campaign', static fn (Builder $campaign): Builder => $campaign->where('status', '!=', CampaignStatus::Cancelled->value));
    }

    /**
     * Most ordered dish (by cups) among the given orders; names are merged without accents and case.
     *
     * @param list<int> $orderIds Orders.
     * @return array{name: string, cups: int}|null
     */
    private function favoriteItem(array $orderIds): ?array
    {
        $items = OrderItem::query()->whereIn('order_id', $orderIds)->get(['item_name', 'quantity']);
        $best = $items->groupBy(static fn (OrderItem $item): string => CampaignItem::normalizeString((string) $item->item_name))
            ->forget('')
            ->map(static fn (Collection $group): array => [
                'name' => (string) $group->groupBy('item_name')->sortByDesc->count()->keys()->first(),
                'cups' => (int) $group->sum('quantity'),
            ])
            ->sortByDesc('cups')
            ->first();

        return $best;
    }

    private function title(int $cups): string
    {
        foreach (self::TITLES as $title => $minimum) {
            if ($cups >= $minimum) {
                return $title;
            }
        }

        return 'connoisseur';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function store(Room $room, RoomUser $member, int $year, ?array $data): void
    {
        if ($data === null) {
            return;
        }
        MemberYearRecap::query()->updateOrCreate(
            ['room_id' => $room->id, 'room_user_id' => $member->id, 'year' => $year],
            ['data' => $data],
        )->touch();
    }

    private function today(Room $room): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone($room))->startOfDay();
    }

    private function timezone(Room $room): string
    {
        return (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
    }
}
