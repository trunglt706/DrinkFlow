<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;

class OrderAggregationService
{
    /**
     * Aggregate ordered items and toppings for a specific campaign in a room.
     *
     * @param int $roomId Room identifier.
     * @param int $campaignId Campaign identifier.
     * @return Collection<int, array{name: string, size: ?string, toppings: string, quantity: int}> Aggregated list of ordered items.
     */
    public function forRoom(int $roomId, int $campaignId): Collection
    {
        $orders = Order::query()
            ->where('room_id', $roomId)
            ->where('campaign_id', $campaignId)
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->with('items.toppings')
            ->get();

        return $orders->flatMap(static fn (Order $order) => $order->items->map(static function ($item): array {
            $toppings = $item->toppings->map(static fn ($topping): string => $topping->topping_name.' x'.$topping->quantity)->implode(', ');

            return [
                'name' => $item->item_name,
                'size' => $item->size_name,
                'quantity' => (int) $item->quantity,
                'toppings' => $toppings,
            ];
        }))
        ->groupBy(static fn (array $item): string => implode('|', [$item['name'], $item['size'] ?? '', $item['toppings']]))
        ->map(static function (Collection $rows): array {
            $first = $rows->first();

            return [
                'name' => $first['name'],
                'size' => $first['size'],
                'toppings' => $first['toppings'],
                'quantity' => (int) $rows->sum('quantity'),
            ];
        })
        ->sortBy([['name', 'asc'], ['size', 'asc']])
        ->values();
    }
}
