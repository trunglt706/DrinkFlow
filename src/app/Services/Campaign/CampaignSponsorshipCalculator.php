<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Money a full sponsorship covers, shared by closing a campaign (debts) and by every screen that shows
 * the sponsor amount before or after closing, so the figures on screen are exactly the debts created.
 *
 * Lines marked "trả riêng" (`order_items.is_self_paid`) are never sponsored: they are billed to the
 * member who ordered them, with their share of delivery fee / discount decided by the campaign's
 * `self_paid_price_basis`. The sponsors split what is left according to their whole-percent shares.
 */
class CampaignSponsorshipCalculator
{
    /**
     * Whether the campaign is fully sponsored by its allocations (sponsors pay everything not self-paid).
     *
     * @param Campaign $campaign Campaign.
     * @return bool True for a full sponsorship with at least one allocation.
     */
    public function isFullSponsorship(Campaign $campaign): bool
    {
        $allocations = collect($campaign->sponsor_allocations ?? []);
        if ($allocations->isEmpty()) {
            return false;
        }

        return $campaign->sponsor_type === Campaign::SPONSOR_TYPE_FULL
            || abs((float) $allocations->sum('percentage') - 100.0) < 0.01;
    }

    /**
     * Per-order split of a fully sponsored campaign into the sponsored pool and self-paid amounts.
     *
     * Delivery fee and discount are spread over the orders by subtotal, then each order's share is
     * divided between its self-paid and sponsorable lines.
     *
     * @param Campaign $campaign Campaign (delivery_fee, discount, self_paid_price_basis).
     * @param Collection<int, Order> $orders Billable (non-cancelled) orders; their items are loaded if missing.
     * @return array{
     *     orders: array<int, array{fee: int, discount: int, sponsored: int, self_paid: int}>,
     *     self_paid_by_user: array<int, int>,
     *     pool: int,
     *     subtotal: int,
     *     fee: int,
     *     discount: int
     * } `orders` is keyed by order ID; `self_paid_by_user` by room_user_id; the totals cover the sponsored part only.
     */
    public function fullSponsorPool(Campaign $campaign, Collection $orders): array
    {
        $orders = $orders->values();
        if ($orders->isNotEmpty()) {
            (new \Illuminate\Database\Eloquent\Collection($orders->all()))->loadMissing('items');
        }

        $priceBasis = (string) ($campaign->self_paid_price_basis ?: Campaign::SELF_PAID_PRICE_BASIS_ORIGINAL);
        $grossSubtotal = (int) $orders->sum('subtotal');
        $deliveryFee = (int) ($campaign->delivery_fee ?? 0);
        $discount = (int) ($campaign->discount ?? 0);

        // Fee and discount split by subtotal so the parts add up exactly (3 x 3,333.33 must give 10,000, not 9,999).
        $weights = $orders->map(static fn (Order $order): int => (int) $order->subtotal)->all();
        $feeParts = self::distribute($deliveryFee, $weights);
        $discountParts = self::distribute($discount, $weights);

        $result = ['orders' => [], 'self_paid_by_user' => [], 'pool' => 0, 'subtotal' => 0, 'fee' => 0, 'discount' => 0];
        foreach ($orders as $index => $order) {
            $orderSubtotal = (int) $order->subtotal;
            $orderFee = $feeParts[$index] ?? 0;
            $orderDiscount = $discountParts[$index] ?? 0;

            [$selfPaidSubtotal, $sponsorableSubtotal] = $this->splitSelfPaidSubtotal($order);
            [$selfPaidFee, $selfPaidDiscount, $sponsorFee, $sponsorDiscount] = $this->splitFeeAndDiscount(
                $priceBasis, $orderSubtotal, $selfPaidSubtotal, $orderFee, $orderDiscount
            );
            $selfPaid = max(0, $selfPaidSubtotal + $selfPaidFee - $selfPaidDiscount);
            $sponsored = max(0, $sponsorableSubtotal + $sponsorFee - $sponsorDiscount);

            $result['orders'][(int) $order->id] = ['fee' => $orderFee, 'discount' => $orderDiscount, 'sponsored' => $sponsored, 'self_paid' => $selfPaid];
            $result['pool'] += $sponsored;
            $result['subtotal'] += $sponsorableSubtotal;
            $result['fee'] += $sponsorFee;
            $result['discount'] += $sponsorDiscount;
            if ($selfPaid > 0) {
                $userId = (int) $order->room_user_id;
                $result['self_paid_by_user'][$userId] = ($result['self_paid_by_user'][$userId] ?? 0) + $selfPaid;
            }
        }

        return $result;
    }

    /**
     * Split an amount in proportion to weights, in whole đồng, so the parts add up to the amount exactly
     * (largest remainder: the parts with the biggest fractions get the leftover đồng, first ones on ties).
     *
     * @param int $amount Amount to split.
     * @param array<int, int> $weights Weights (e.g. order subtotals), keyed like the result.
     * @return array<int, int> Parts keyed like the weights; all zero when the weights sum to zero.
     */
    public static function distribute(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0 || $amount === 0) {
            return array_map(static fn (): int => 0, $weights);
        }

        $parts = [];
        $fractions = [];
        foreach ($weights as $key => $weight) {
            $exact = $amount * $weight / $total;
            $parts[$key] = (int) floor($exact);
            $fractions[$key] = $exact - $parts[$key];
        }
        $left = $amount - array_sum($parts);
        arsort($fractions);
        foreach (array_keys($fractions) as $key) {
            if ($left <= 0) {
                break;
            }
            $parts[$key]++;
            $left--;
        }

        return $parts;
    }

    /**
     * Split a sponsored pool between the sponsors by their percentage.
     *
     * Rounding leftovers go to the first sponsor so the shares add up to the pool exactly. Each share's
     * breakdown satisfies `items + fee - discount = amount`, which is how a Debt stores it
     * (original_amount + adjustment_amount = remaining_amount when nothing is paid yet).
     *
     * @param Campaign $campaign Campaign holding `sponsor_allocations` (room_user_id, percentage).
     * @param array{pool: int, fee: int, discount: int} $pool Result of {@see fullSponsorPool()}.
     * @return array<int, array{room_user_id: int, percentage: float, amount: int, items: int, fee: int, discount: int}> Shares in allocation order.
     */
    public function allocate(Campaign $campaign, array $pool): array
    {
        $shares = [];
        foreach (collect($campaign->sponsor_allocations ?? []) as $allocation) {
            $roomUserId = (int) ($allocation['room_user_id'] ?? 0);
            $percentage = (float) ($allocation['percentage'] ?? 0);
            if ($roomUserId <= 0 || $percentage <= 0) {
                continue;
            }
            $shares[] = [
                'room_user_id' => $roomUserId,
                'percentage' => $percentage,
                'amount' => (int) round(($pool['pool'] * $percentage) / 100),
                'fee' => (int) round(($pool['fee'] * $percentage) / 100),
                'discount' => (int) round(($pool['discount'] * $percentage) / 100),
            ];
        }

        if ($shares !== []) {
            $shares[0]['amount'] = max(0, $shares[0]['amount'] + $pool['pool'] - array_sum(array_column($shares, 'amount')));
        }

        // Items part derived from the amount, so the stored breakdown always adds up to the debt.
        return array_map(static fn (array $share): array => $share + [
            'items' => $share['amount'] - $share['fee'] + $share['discount'],
        ], $shares);
    }

    /**
     * Split an order's subtotal into its self-paid lines and the lines still eligible for sponsorship.
     *
     * @param Order $order Order with its items loaded.
     * @return array{0: int, 1: int} [selfPaidSubtotal, sponsorableSubtotal]
     */
    public function splitSelfPaidSubtotal(Order $order): array
    {
        $selfPaidSubtotal = (int) $order->items
            ->where('is_self_paid', true)
            ->sum('line_subtotal');

        return [$selfPaidSubtotal, max(0, (int) $order->subtotal - $selfPaidSubtotal)];
    }

    /**
     * Split a delivery fee / discount pair between the self-paid and sponsorable portions of an order,
     * according to the campaign's self-paid price basis.
     *
     * - `original`: self-paid items pay their raw price only; the sponsorable portion absorbs the whole
     *   delivery fee and discount.
     * - `campaign_prorated`: fee and discount are split by each portion's share of the subtotal.
     *
     * @param string $priceBasis One of Campaign::SELF_PAID_PRICE_BASIS_*.
     * @param int $subtotal Total subtotal (self-paid + sponsorable).
     * @param int $selfPaidSubtotal Portion of $subtotal that is self-paid.
     * @param int $fee Delivery fee already allocated to this subtotal.
     * @param int $discount Discount already allocated to this subtotal.
     * @return array{0: int, 1: int, 2: int, 3: int} [selfPaidFee, selfPaidDiscount, sponsorFee, sponsorDiscount]
     */
    public function splitFeeAndDiscount(string $priceBasis, int $subtotal, int $selfPaidSubtotal, int $fee, int $discount): array
    {
        if ($selfPaidSubtotal <= 0 || $priceBasis !== Campaign::SELF_PAID_PRICE_BASIS_CAMPAIGN_PRORATED) {
            return [0, 0, $fee, $discount];
        }

        $selfPaidRatio = $subtotal > 0 ? ($selfPaidSubtotal / $subtotal) : 0;
        $selfPaidFee = (int) round($fee * $selfPaidRatio);
        $selfPaidDiscount = (int) round($discount * $selfPaidRatio);

        return [$selfPaidFee, $selfPaidDiscount, $fee - $selfPaidFee, $discount - $selfPaidDiscount];
    }
}
