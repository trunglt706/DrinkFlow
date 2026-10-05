<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\Order;
use App\Support\Privacy\PersonalDataMasker;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicOrderCheckService
{
    /**
     * Build the stable hash segment used in a campaign order-check URL.
     *
     * @param Campaign $campaign Campaign associated with the link.
     * @return string Hash segment.
     */
    public function hash(Campaign $campaign): string
    {
        return hash_hmac('sha256', $campaign->id.'|'.$campaign->code, (string) config('app.key'));
    }

    /**
     * Verify the hash segment belongs to the campaign.
     *
     * @param Campaign $campaign Campaign from the route.
     * @param string $hash Hash segment from the route.
     * @return bool True when the hash matches.
     */
    public function hashMatches(Campaign $campaign, string $hash): bool
    {
        return hash_equals($this->hash($campaign), $hash);
    }

    /**
     * Determine whether the campaign is eligible for public order lookup.
     *
     * @param Campaign $campaign Campaign to inspect.
     * @return bool True for every campaign except drafts and cancelled ones.
     */
    public function isCheckable(Campaign $campaign): bool
    {
        return $campaign->status !== null
            && ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Cancelled], true);
    }

    /**
     * Find orders in the campaign matching one order code, email, phone, or room code.
     *
     * @param Campaign $campaign Checkable campaign to search.
     * @param string $identifier User-supplied lookup value.
     * @return Collection<int, Order> Matching orders.
     */
    public function findOrders(Campaign $campaign, string $identifier): Collection
    {
        $needle = Str::lower(trim($identifier));
        $phoneNeedle = preg_replace('/[^0-9+]/', '', $needle) ?: '';

        return Order::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->with([
                'roomUser.globalUser',
                'items.toppings',
                'children.roomUser.globalUser',
                'children.items.toppings',
                'parent.roomUser.globalUser',
            ])
            ->get()
            ->filter(static function (Order $order) use ($needle, $phoneNeedle): bool {
                $roomUser = $order->roomUser;
                $globalUser = $roomUser?->globalUser;
                $email = Str::lower((string) ($globalUser?->email ?? ''));
                $code = Str::lower((string) ($order->code ?? ''));
                $userCode = Str::lower((string) ($roomUser?->user_code ?? ''));
                $globalCode = Str::lower((string) ($globalUser?->code ?? ''));
                $phone = preg_replace('/[^0-9+]/', '', (string) ($globalUser?->phone ?? '')) ?: '';

                return $needle === $code
                    || $needle === $userCode
                    || $needle === $globalCode
                    || $needle === $email
                    || ($phoneNeedle !== '' && $phoneNeedle === $phone);
            })
            ->values();
    }

    /**
     * Serialize matching orders into a safe public response payload.
     *
     * @param Collection<int, Order> $orders Matching orders.
     * @return array<int, array<string, mixed>> Public order summaries.
     */
    public function summaries(Collection $orders): array
    {
        return $orders->map(function (Order $order): array {
            $recipient = $order->roomUser;
            $orderedBy = $order->parent?->roomUser;

            return [
                'code' => $order->code,
                'status' => $order->status?->value ?? (string) $order->status,
                'status_label' => $order->status?->label() ?? (string) $order->status,
                'member_name' => $recipient?->display_name ?: $recipient?->globalUser?->name,
                'member_email' => PersonalDataMasker::email($recipient?->globalUser?->email),
                'note' => $order->note,
                'ordered_by' => $this->personSummary($orderedBy),
                'subtotal' => (int) $order->subtotal,
                'final_amount' => (int) $order->final_amount,
                'items' => $order->items->map(fn ($item): array => $this->itemSummary($item))->values()->all(),
                'proxy_orders' => $order->children->map(function (Order $child): array {
                    $childRecipient = $child->roomUser;

                    return [
                        'code' => $child->code,
                        'recipient' => $this->personSummary($childRecipient),
                        'note' => $child->note,
                        'items' => $child->items->map(fn ($item): array => $this->itemSummary($item))->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Aggregate quantities for the matched orders and their proxy orders.
     *
     * @param Collection<int, Order> $orders Matching orders.
     * @return array<int, array{name: string, quantity: int, options: array<int, string>}> Aggregated items.
     */
    public function itemTotals(Collection $orders): array
    {
        $totals = [];
        $addItems = function (Collection $items) use (&$totals): void {
            foreach ($items as $item) {
                $summary = $this->itemSummary($item);
                $key = $summary['name'].'|'.implode('|', $summary['options']).'|'.implode('|', $summary['toppings']);
                if (!isset($totals[$key])) {
                    $totals[$key] = [
                        'name' => $summary['name'],
                        'quantity' => 0,
                        'options' => $summary['options'],
                        'toppings' => $summary['toppings'],
                    ];
                }
                $totals[$key]['quantity'] += $summary['quantity'];
            }
        };

        foreach ($orders as $order) {
            $addItems($order->items);
            foreach ($order->children as $child) {
                $addItems($child->items);
            }
        }

        return array_values($totals);
    }

    /**
     * Build the public-safe person details used for proxy-order context.
     *
     * @param \App\Models\RoomUser|null $roomUser Recipient or ordering member.
     * @return array<string, string|null> Public person details.
     */
    private function personSummary(?\App\Models\RoomUser $roomUser): array
    {
        return [
            'name' => $roomUser?->display_name ?: $roomUser?->globalUser?->name,
            'email' => PersonalDataMasker::email($roomUser?->globalUser?->email),
        ];
    }

    /**
     * Build a public-safe order item including its options and toppings.
     *
     * @param \App\Models\OrderItem $item Order item to serialize.
     * @return array<string, mixed> Public item details.
     */
    private function itemSummary(\App\Models\OrderItem $item): array
    {
        $options = array_values(array_filter([
            $item->size_name,
            $item->ice_percent !== null ? $item->ice_percent.'% ice' : null,
            $item->sugar_percent !== null ? $item->sugar_percent.'% sugar' : null,
        ]));

        return [
            'name' => $item->item_name,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unit_price,
            'line_subtotal' => (int) $item->line_subtotal,
            'options' => $options,
            'toppings' => $item->toppings->map(static fn ($topping): string => (string) $topping->topping_name)->values()->all(),
            'note' => $item->note,
        ];
    }
}
