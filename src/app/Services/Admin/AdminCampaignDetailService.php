<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Services\Campaign\CampaignSponsorshipCalculator;
use Illuminate\Support\Collection;

class AdminCampaignDetailService
{
    public function __construct(private readonly CampaignSponsorshipCalculator $sponsorship)
    {
    }

    /**
     * Build aggregated financial, participation and item details for campaign view.
     *
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @return array<string, mixed> Aggregated view payload.
     */
    public function getCampaignViewData(Room $room, Campaign $campaign): array
    {
        $campaign->load([
            'items.sizes',
            'items.toppings',
            'paymentAccount',
            'orders.roomUser.globalUser',
            'orders.parent.roomUser.globalUser',
            'orders.items.toppings',
            'debts.roomUser.globalUser',
        ]);

        $orders = $campaign->orders
            ->filter(static fn (Order $order): bool => $order->status !== OrderStatus::Cancelled && $order->cancelled_at === null)
            ->values();
        $allActiveRoomUsers = $room->roomUsers()
            ->where('status', RoomUserStatus::Active)
            ->with('globalUser')
            ->get();
        $totalUsersCount = $allActiveRoomUsers->count() ?: 1;
        $orderedUserIds = $orders->pluck('room_user_id')->unique()->filter()->all();
        $orderedUsersCount = count($orderedUserIds);

        $declinedParticipantRoomUserIds = CampaignParticipant::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignParticipant::STATUS_DECLINED)
            ->whereIn('room_user_id', $allActiveRoomUsers->pluck('id'))
            ->pluck('room_user_id')
            ->all();

        $declinedUsers = $allActiveRoomUsers->whereIn('id', $declinedParticipantRoomUserIds)->values();
        $declinedUsersCount = $declinedUsers->count();

        $orderedAndDeclinedIds = array_unique(array_merge($orderedUserIds, $declinedParticipantRoomUserIds));
        $unresponsiveUsers = $allActiveRoomUsers->whereNotIn('id', $orderedAndDeclinedIds)->values();
        $pendingUsersCount = $unresponsiveUsers->count();

        $aggregatedItems = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $toppingLabels = $item->toppings
                    ->map(static fn ($topping): string => (($topping->quantity > 1 ? $topping->quantity.'x ' : '').trim((string) $topping->topping_name)))
                    ->filter(static fn (string $label): bool => trim($label) !== '')
                    ->sort()
                    ->values();
                $note = trim((string) ($item->note ?? ''));
                // Different toppings / ice / sugar / note are different drinks for the store, so they get their own line.
                $key = implode('|', [$item->item_name, $item->size_name ?? '', $toppingLabels->join(';'), $item->ice_percent ?? '', $item->sugar_percent ?? '', $note]);
                if (! $aggregatedItems->has($key)) {
                    $aggregatedItems->put($key, [
                        'name' => $item->item_name,
                        'size' => $item->size_name,
                        'unit_price' => $item->unit_price,
                        'quantity' => 0,
                        'member_quantities' => collect(),
                        'total_amount' => 0,
                        'notes' => collect(),
                        'toppings' => $toppingLabels,
                        'ice_percent' => $item->ice_percent,
                        'sugar_percent' => $item->sugar_percent,
                    ]);
                }
                $curr = $aggregatedItems->get($key);
                $curr['quantity'] += $item->quantity;
                $memberKey = $order->room_user_id ?? 'order-'.$order->id;
                $memberQuantity = $curr['member_quantities']->get($memberKey, [
                    'name' => $order->roomUser?->display_name ?? __('admin.member'),
                    'quantity' => 0,
                ]);
                $memberQuantity['quantity'] += $item->quantity;
                $curr['member_quantities']->put($memberKey, $memberQuantity);
                $curr['total_amount'] += $item->line_subtotal;
                if ($note !== '') {
                    $curr['notes']->push($note);
                }
                $aggregatedItems->put($key, $curr);
            }
        }

        $grossSubtotal = (int) $orders->sum('subtotal');
        $grossTotal = max(0, $grossSubtotal + (int) ($campaign->delivery_fee ?? 0) - (int) ($campaign->discount ?? 0));
        // Full sponsorship covers everything except "trả riêng" lines: same figures as the debts written on close.
        $isFullSponsorship = $this->sponsorship->isFullSponsorship($campaign);
        $sponsorPool = $isFullSponsorship ? $this->sponsorship->fullSponsorPool($campaign, $orders) : null;
        $sponsorSubsidy = $sponsorPool !== null ? $sponsorPool['pool'] : (int) $orders->sum('sponsor_amount');

        // "Trả riêng" lines are billed to their member (never sponsored); same per-member figures as the debts written on close.
        $selfPaidByUser = ($sponsorPool ?? $this->sponsorship->fullSponsorPool($campaign, $orders))['self_paid_by_user'];
        $orderOwners = $orders->pluck('roomUser', 'room_user_id');
        $selfPaidMembers = collect($selfPaidByUser)
            ->map(static fn (int $amount, int $roomUserId): array => [
                'room_user_id' => $roomUserId,
                'name' => $orderOwners->get($roomUserId)?->display_name ?? __('admin.member'),
                'amount' => $amount,
            ])
            ->sortByDesc('amount')
            ->values();
        $selfPaidTotal = (int) $selfPaidMembers->sum('amount');
        $netPayables = (int) $orders->sum('final_amount');
        $paidViaQr = (int) $campaign->debts->sum('paid_amount');
        $memberDebt = (int) $campaign->debts->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])->sum('remaining_amount');

        $sponsorAllocationsData = collect($campaign->sponsor_allocations ?? []);
        $sponsorUserIds = $sponsorAllocationsData->pluck('room_user_id')->filter()->map(fn($id) => (int) $id);
        $sponsorRoomUsers = $sponsorUserIds->isNotEmpty()
            ? $room->roomUsers()->with('globalUser')->whereIn('id', $sponsorUserIds)->get()->keyBy('id')
            : collect();

        // Each sponsor's amount is exactly its debt on close (rounding remainder on the first sponsor).
        $sponsorShares = $sponsorPool !== null
            ? collect($this->sponsorship->allocate($campaign, $sponsorPool))->keyBy('room_user_id')
            : collect();
        $sponsorsList = $sponsorAllocationsData->map(function ($alloc) use ($sponsorRoomUsers, $sponsorSubsidy, $sponsorShares): array {
            $roomUserId = (int) ($alloc['room_user_id'] ?? 0);
            $user = $sponsorRoomUsers->get($roomUserId);
            $name = $user?->display_name ?? $user?->globalUser?->name ?? __('admin.sponsor_info');
            $percentage = (float) ($alloc['percentage'] ?? 0);

            return [
                'name' => $name,
                // Whole percents are shown without decimals (older campaigns may store 33.33).
                'percentage' => floor($percentage) === $percentage ? (int) $percentage : round($percentage, 2),
                'amount' => (int) ($sponsorShares->get($roomUserId)['amount'] ?? round(($sponsorSubsidy * $percentage) / 100)),
                'avatar' => $user?->globalUser?->avatar_url ?? null,
            ];
        })->values();

        if ($sponsorsList->isEmpty() && !empty($campaign->sponsor_name)) {
            $sponsorsList->push([
                'name' => $campaign->sponsor_name,
                'percentage' => 100,
                'amount' => $sponsorSubsidy,
                'avatar' => null,
            ]);
        }

        $departmentGroups = collect();
        foreach ($orders as $order) {
            $user = $order->roomUser;
            $globalUser = $user?->globalUser;
            $deskLocation = self::normalizeDeskLocation($globalUser?->desk_location);
            $department = $deskLocation !== '' ? mb_strtolower($deskLocation) : '';

            if (! $departmentGroups->has($department)) {
                $departmentGroups->put($department, [
                    'department' => $deskLocation !== '' ? $deskLocation : __('admin.unassigned_department'),
                    'is_unassigned' => $deskLocation === '',
                    'orders' => collect(),
                    'members' => collect(),
                    'items' => collect(),
                    'total_quantity' => 0,
                    'total_amount' => 0,
                ]);
            }

            $dept = $departmentGroups->get($department);
            $dept['orders']->push($order);
            if ($user && ! $dept['members']->contains('id', $user->id)) {
                $dept['members']->push($user);
            }

            foreach ($order->items as $item) {
                $toppingLabels = $item->toppings
                    ->map(static fn ($topping): string => (($topping->quantity > 1 ? $topping->quantity.'x ' : '').trim((string) $topping->topping_name)))
                    ->filter(static fn (string $label): bool => trim($label) !== '')
                    ->sort()
                    ->values();
                $note = trim((string) ($item->note ?? ''));
                // Same rule as the aggregated list: other toppings / ice / sugar / note mean a separate line.
                $itemKey = implode('|', [$item->item_name, $item->size_name ?? '', $toppingLabels->join(';'), $item->ice_percent ?? '', $item->sugar_percent ?? '', $note]);
                if (! $dept['items']->has($itemKey)) {
                    $dept['items']->put($itemKey, [
                        'name' => $item->item_name,
                        'size' => $item->size_name,
                        'unit_price' => $item->unit_price,
                        'quantity' => 0,
                        'total_amount' => 0,
                        'notes' => collect(),
                        'members' => collect(),
                        'toppings' => $toppingLabels,
                        'ice_percent' => $item->ice_percent,
                        'sugar_percent' => $item->sugar_percent,
                    ]);
                }
                $deptItem = $dept['items']->get($itemKey);
                $deptItem['quantity'] += $item->quantity;
                $deptItem['total_amount'] += $item->line_subtotal;
                if ($note !== '') {
                    $deptItem['notes']->push($note);
                }
                $memberName = $user?->display_name ?? __('admin.member');
                $deptItem['members']->push($memberName . ($item->quantity > 1 ? " (x{$item->quantity})" : ''));
                $dept['items']->put($itemKey, $deptItem);

                $dept['total_quantity'] += $item->quantity;
                $dept['total_amount'] += $item->line_subtotal;
            }

            $departmentGroups->put($department, $dept);
        }

        // Each line's payable share of the campaign total (delivery fee added, discount deducted), prorated on its
        // original amount. Department totals take their share of the campaign total, then split it over their lines,
        // so the lines of a department always add up to its total.
        $aggregatedItems = self::withPayableAmounts($aggregatedItems, $grossTotal);
        $departmentGroups = self::withPayableAmounts($departmentGroups, $grossTotal)
            ->map(static function (array $dept): array {
                $dept['items'] = self::withPayableAmounts($dept['items'], $dept['payable_amount']);

                return $dept;
            });

        return [
            'room' => $room,
            'campaign' => $campaign,
            // Newest order first for display; the sums above keep the creation order (rounding remainders follow it).
            'orders' => $orders->sortByDesc(static fn (Order $order): array => [$order->created_at?->getTimestamp() ?? 0, $order->id])->values(),
            'aggregatedItems' => $aggregatedItems->values(),
            'departmentGroups' => $departmentGroups
                ->sort(static fn (array $a, array $b): int => [$a['is_unassigned'], mb_strtolower($a['department'])] <=> [$b['is_unassigned'], mb_strtolower($b['department'])])
                ->values(),
            'totalUsersCount' => $totalUsersCount,
            'orderedUsersCount' => $orderedUsersCount,
            'declinedUsersCount' => $declinedUsersCount,
            'pendingUsersCount' => $pendingUsersCount,
            'declinedUsers' => $declinedUsers,
            'unresponsiveUsers' => $unresponsiveUsers,
            'grossSubtotal' => $grossSubtotal,
            'grossTotal' => $grossTotal,
            'sponsorSubsidy' => $sponsorSubsidy,
            'netPayables' => $netPayables,
            'paidViaQr' => $paidViaQr,
            'memberDebt' => $memberDebt,
            'sponsorsList' => $sponsorsList,
            'selfPaidTotal' => $selfPaidTotal,
            'selfPaidMembers' => $selfPaidMembers,
        ];
    }

    /**
     * Ordering progress of the room's active members for a campaign (same rules as the campaign page: cancelled
     * orders are ignored, only active members count).
     *
     * @param Room $room Room that owns the campaign.
     * @param Campaign $campaign Campaign being followed.
     * @return array{total: int, ordered: int, declined: int, pending: int, ordered_percent: float, declined_percent: float}
     */
    public function getParticipation(Room $room, Campaign $campaign): array
    {
        $activeRoomUserIds = $room->roomUsers()->where('status', RoomUserStatus::Active)->pluck('id');
        $orderedUserIds = $campaign->orders()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('cancelled_at')
            ->whereIn('room_user_id', $activeRoomUserIds)
            ->distinct()
            ->pluck('room_user_id');
        $declined = CampaignParticipant::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignParticipant::STATUS_DECLINED)
            ->whereIn('room_user_id', $activeRoomUserIds)
            ->whereNotIn('room_user_id', $orderedUserIds)
            ->distinct()
            ->count('room_user_id');
        $total = $activeRoomUserIds->count();
        $ordered = $orderedUserIds->count();

        return [
            'total' => $total,
            'ordered' => $ordered,
            'declined' => $declined,
            'pending' => max(0, $total - $ordered - $declined),
            'ordered_percent' => $total > 0 ? round($ordered / $total * 100, 1) : 0.0,
            'declined_percent' => $total > 0 ? round($declined / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Build the fresh summary shown in the close-campaign confirmation modal.
     *
     * Uses the same rules as getCampaignViewData() (cancelled orders excluded, only active room members
     * counted, same sponsor/total formulas) but only loads the columns it needs, because the modal
     * re-fetches it every time an admin opens it.
     *
     * Item groups do not overlap: proxy items live in child orders (parent_id set) and can never be
     * self-paid, self-paid items are flagged per item, and own items are everything else.
     *
     * @param Room $room Room that owns the campaign.
     * @param Campaign $campaign Campaign about to be closed.
     * @return array<string, int|string|bool|null> Campaign identity, item/member counts and money totals (VND).
     */
    public function getCloseSummary(Room $room, Campaign $campaign): array
    {
        $campaign->refresh();

        $orders = $campaign->orders()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('cancelled_at')
            ->get(['id', 'parent_id', 'room_user_id', 'subtotal', 'sponsor_amount']);
        $proxyOrderIds = $orders->whereNotNull('parent_id')->pluck('id')->all();
        $items = OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->get(['order_id', 'quantity', 'line_subtotal', 'is_self_paid']);

        $proxyItems = (int) $items->whereIn('order_id', $proxyOrderIds)->sum('quantity');
        $selfPaidLines = $items->whereNotIn('order_id', $proxyOrderIds)->where('is_self_paid', true);
        $selfPaidItems = (int) $selfPaidLines->sum('quantity');
        // Same base as CloseCampaignAction::splitSelfPaidSubtotal(): line subtotals before fee/discount split.
        $selfPaidTotal = (int) $selfPaidLines->sum('line_subtotal');
        $totalItems = (int) $items->sum('quantity');

        $activeRoomUserIds = $room->roomUsers()->where('status', RoomUserStatus::Active)->pluck('id');
        $orderedUserIds = $orders->pluck('room_user_id')->filter()->unique();
        $declinedUsersCount = CampaignParticipant::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignParticipant::STATUS_DECLINED)
            ->whereIn('room_user_id', $activeRoomUserIds)
            ->whereNotIn('room_user_id', $orderedUserIds)
            ->distinct()
            ->count('room_user_id');
        $pendingUsersCount = max(0, $activeRoomUserIds->diff($orderedUserIds)->count() - $declinedUsersCount);

        $grossSubtotal = (int) $orders->sum('subtotal');
        $deliveryFee = (int) ($campaign->delivery_fee ?? 0);
        $discount = (int) ($campaign->discount ?? 0);
        $grossTotal = $campaign->grossTotal($grossSubtotal);
        // Self-paid lines are billed to their member, never to the sponsors (same rule as the close action).
        $sponsorTotal = $this->sponsorship->isFullSponsorship($campaign)
            ? $this->sponsorship->fullSponsorPool($campaign, $orders)['pool']
            : (int) $orders->sum('sponsor_amount');

        return [
            'id' => (int) $campaign->id,
            'code' => $campaign->code,
            'name' => (string) $campaign->name,
            'is_closable' => in_array($campaign->status, [CampaignStatus::Active, CampaignStatus::Closing], true),
            'own_items' => $totalItems - $proxyItems - $selfPaidItems,
            'proxy_items' => $proxyItems,
            'self_paid_items' => $selfPaidItems,
            'total_items' => $totalItems,
            'gross_subtotal' => $grossSubtotal,
            'discount_total' => $discount,
            'extra_fee_total' => $deliveryFee,
            'sponsor_total' => $sponsorTotal,
            'self_paid_total' => $selfPaidTotal,
            'final_total' => max(0, $grossTotal - $sponsorTotal),
            'orders_count' => $orders->count(),
            'ordered_users_count' => $orderedUserIds->count(),
            'pending_users_count' => $pendingUsersCount,
            'declined_users_count' => $declinedUsersCount,
            'total_users_count' => $activeRoomUserIds->count(),
        ];
    }

    /**
     * Split a payable total over rows in proportion to their original `total_amount`.
     *
     * Uses the largest remainder method so the rounded shares add up exactly to the given total.
     *
     * @param Collection<array-key, array<string, mixed>> $rows Rows carrying an integer `total_amount` (original price).
     * @param int $payableTotal Amount to split (original total + delivery fee - discount).
     * @return Collection<array-key, array<string, mixed>> Same rows with a `payable_amount` key added.
     */
    private static function withPayableAmounts(Collection $rows, int $payableTotal): Collection
    {
        $originalTotal = (int) $rows->sum('total_amount');
        if ($originalTotal <= 0) {
            return $rows->map(static fn (array $row): array => $row + ['payable_amount' => 0]);
        }

        $shares = $rows->map(static fn (array $row): float => ((int) $row['total_amount'] * $payableTotal) / $originalTotal);
        $payables = $shares->map(static fn (float $share): int => (int) floor($share));
        $remainder = $payableTotal - (int) $payables->sum();
        $shares->sortByDesc(static fn (float $share): float => $share - floor($share))
            ->keys()
            ->take($remainder)
            ->each(static function ($key) use ($payables): void {
                $payables->put($key, $payables->get($key) + 1);
            });

        return $rows->map(static fn (array $row, $key): array => $row + ['payable_amount' => $payables->get($key)]);
    }

    /**
     * Normalize a desk location so that spacing and letter case differences fall into one group.
     *
     * @param string|null $deskLocation Raw desk_location value from the member's global profile.
     * @return string Trimmed value with collapsed whitespace, or an empty string when no location is set.
     */
    public static function normalizeDeskLocation(?string $deskLocation): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $deskLocation));
    }
}
