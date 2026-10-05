<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\OrderStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Campaign\CampaignSponsorshipCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SponsorLeaderboardService
{
    public function __construct(private readonly CampaignSponsorshipCalculator $sponsorship)
    {
    }

    /**
     * Rank the actual sponsors of a room's campaigns (the people/entities who funded the
     * subsidy), never the members who merely benefited from a sponsored order.
     *
     * A campaign's sponsor is resolved the same way the campaign detail page does: for
     * sponsor_type "full" the order total minus the "trả riêng" (self-paid) lines is the sponsored
     * amount, split across `sponsor_allocations` exactly like the sponsor debts written on close
     * ({@see CampaignSponsorshipCalculator}); for any other sponsor
     * type, the sponsored amount is `SUM(orders.sponsor_amount)` and attributed to the free-text
     * `sponsor_name` (e.g. a department or external sponsor with no room account).
     *
     * @param Room $room Target room.
     * @param Carbon|null $from Inclusive campaign creation period start; null = no lower bound.
     * @param Carbon|null $to Inclusive campaign creation period end; null = no upper bound.
     * @param int|null $limit Maximum rows to return; null = unlimited.
     * @param list<OrderStatus>|null $orderStatuses Only count orders in these statuses; null = every non-cancelled order.
     * @param list<CampaignStatus>|null $campaignStatuses Optional campaign lifecycle filter.
     * @return Collection<int, array{room_user_id: ?int, user_name: string, user_email: ?string, sponsored_campaigns: int, total_sponsored: int}> Highest total first.
     */
    public function build(Room $room, ?Carbon $from = null, ?Carbon $to = null, ?int $limit = null, ?array $orderStatuses = null, ?array $campaignStatuses = null): Collection
    {
        $campaignsQuery = Campaign::query()->where('room_id', $room->id);
        if ($campaignStatuses !== null) {
            $campaignsQuery->whereIn('status', array_map(static fn (CampaignStatus $status): string => $status->value, $campaignStatuses));
        }
        if ($from !== null && $to !== null) {
            $campaignsQuery->whereBetween('created_at', [$from, $to]);
        }
        $campaigns = $campaignsQuery->get(['id', 'sponsor_name', 'sponsor_type', 'sponsor_allocations', 'delivery_fee', 'discount', 'self_paid_price_basis']);

        if ($campaigns->isEmpty()) {
            return collect();
        }

        $statusFilter = function ($query) use ($orderStatuses): void {
            $orderStatuses !== null
                ? $query->whereIn('status', array_map(static fn (OrderStatus $status): string => $status->value, $orderStatuses))
                : $query->whereNotIn('status', [OrderStatus::Cancelled->value]);
        };

        $orderTotalsByCampaign = Order::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->where($statusFilter)
            ->selectRaw('campaign_id, SUM(subtotal) as gross_subtotal, SUM(sponsor_amount) as sponsor_amount_total')
            ->groupBy('campaign_id')
            ->get()
            ->keyBy('campaign_id');

        // Fully sponsored campaigns need their order lines to leave the self-paid ones out.
        $fullSponsorCampaigns = $campaigns->filter(fn (Campaign $campaign): bool => $this->sponsorship->isFullSponsorship($campaign));
        $fullSponsorOrders = $fullSponsorCampaigns->isEmpty() ? collect() : Order::query()
            ->whereIn('campaign_id', $fullSponsorCampaigns->pluck('id'))
            ->where($statusFilter)
            ->with('items:id,order_id,line_subtotal,is_self_paid')
            ->get(['id', 'campaign_id', 'room_user_id', 'subtotal'])
            ->groupBy('campaign_id');
        $sponsorSharesByCampaign = $fullSponsorCampaigns->mapWithKeys(fn (Campaign $campaign): array => [
            $campaign->id => collect($this->sponsorship->allocate(
                $campaign,
                $this->sponsorship->fullSponsorPool($campaign, $fullSponsorOrders->get($campaign->id, collect())),
            ))->keyBy('room_user_id'),
        ]);

        $totals = $this->accumulateSponsorTotals($campaigns, $orderTotalsByCampaign, $sponsorSharesByCampaign);

        return $this->resolveLeaderboard($totals, $limit);
    }

    /**
     * Sum each campaign's sponsor subsidy into per-sponsor running totals.
     *
     * @param Collection<int, Campaign> $campaigns Campaigns to attribute.
     * @param Collection<int, object{gross_subtotal: int, sponsor_amount_total: int}> $orderTotalsByCampaign Order aggregates keyed by campaign id.
     * @param Collection<int, Collection<int, array{amount: int}>> $sponsorSharesByCampaign Full-sponsorship shares keyed by campaign id, then room_user_id.
     * @return array<string, array{room_user_id: ?int, name: ?string, campaigns: int, amount: int}> Running totals keyed by sponsor identity.
     */
    private function accumulateSponsorTotals(Collection $campaigns, Collection $orderTotalsByCampaign, Collection $sponsorSharesByCampaign): array
    {
        $totals = [];

        foreach ($campaigns as $campaign) {
            $orderTotals = $orderTotalsByCampaign->get($campaign->id);
            if ($orderTotals === null) {
                continue;
            }

            $shares = $sponsorSharesByCampaign->get($campaign->id);
            $sponsorSubsidy = $shares !== null
                ? (int) $shares->sum('amount')
                : (int) $orderTotals->sponsor_amount_total;

            if ($sponsorSubsidy <= 0) {
                continue;
            }

            $allocations = collect($campaign->sponsor_allocations ?? []);

            if ($allocations->isEmpty()) {
                if (empty($campaign->sponsor_name)) {
                    continue;
                }
                $key = 'name:'.mb_strtolower(trim((string) $campaign->sponsor_name));
                $totals[$key] ??= ['room_user_id' => null, 'name' => $campaign->sponsor_name, 'campaigns' => 0, 'amount' => 0];
                $totals[$key]['campaigns']++;
                $totals[$key]['amount'] += $sponsorSubsidy;
                continue;
            }

            foreach ($allocations as $allocation) {
                $roomUserId = (int) ($allocation['room_user_id'] ?? 0);
                if ($roomUserId <= 0) {
                    continue;
                }
                $percentage = (float) ($allocation['percentage'] ?? 0);
                $amount = (int) ($shares?->get($roomUserId)['amount'] ?? round(($sponsorSubsidy * $percentage) / 100));
                $key = 'user:'.$roomUserId;
                $totals[$key] ??= ['room_user_id' => $roomUserId, 'name' => null, 'campaigns' => 0, 'amount' => 0];
                $totals[$key]['campaigns']++;
                $totals[$key]['amount'] += $amount;
            }
        }

        return $totals;
    }

    /**
     * Resolve room member names/emails and sort the accumulated sponsor totals.
     *
     * @param array<string, array{room_user_id: ?int, name: ?string, campaigns: int, amount: int}> $totals Running totals keyed by sponsor identity.
     * @param int|null $limit Maximum rows to return; null = unlimited.
     * @return Collection<int, array{room_user_id: ?int, user_name: string, user_email: ?string, sponsored_campaigns: int, total_sponsored: int}> Highest total first.
     */
    private function resolveLeaderboard(array $totals, ?int $limit): Collection
    {
        if (empty($totals)) {
            return collect();
        }

        $roomUserIds = collect($totals)->pluck('room_user_id')->filter()->unique()->values();
        $roomUsersById = $roomUserIds->isNotEmpty()
            ? RoomUser::with('globalUser')->whereIn('id', $roomUserIds)->get()->keyBy('id')
            : collect();

        $leaderboard = collect($totals)->map(function (array $row) use ($roomUsersById): array {
            $roomUser = $row['room_user_id'] !== null ? $roomUsersById->get($row['room_user_id']) : null;

            return [
                'room_user_id' => $row['room_user_id'],
                'user_name' => $roomUser?->display_name ?? $roomUser?->globalUser?->name ?? $row['name'] ?? __('admin.sponsor_info'),
                'user_email' => $roomUser?->globalUser?->email,
                'sponsored_campaigns' => $row['campaigns'],
                'total_sponsored' => $row['amount'],
            ];
        })->sortByDesc('total_sponsored')->values();

        return $limit !== null ? $leaderboard->take($limit)->values() : $leaderboard;
    }
}
