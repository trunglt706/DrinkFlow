<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->resource->status;
        $paymentStatus = $this->resource->payment_status;

        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'payment_method' => $this->resource->payment_method,
            'subtotal' => (int) $this->resource->subtotal,
            'delivery_amount' => (int) ($this->resource->delivery_amount ?? 0),
            'discount_amount' => (int) ($this->resource->discount_amount ?? 0),
            'sponsor_amount' => (int) ($this->resource->sponsor_amount ?? 0),
            'final_amount' => (int) $this->resource->final_amount,
            'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
            'payment_status' => $paymentStatus instanceof BackedEnum ? $paymentStatus->value : (string) $paymentStatus,
            'note' => $this->resource->note,
            'submitted_at' => $this->resource->submitted_at?->toISOString(),
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'items' => $this->when(
                $this->resource->relationLoaded('items'),
                fn (): array => $this->resource->items->map(static fn ($item): array => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'size_name' => $item->size_name,
                    'unit_price' => (int) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'ice_percent' => $item->ice_percent,
                    'sugar_percent' => $item->sugar_percent,
                    'line_subtotal' => (int) $item->line_subtotal,
                    'note' => $item->note,
                    'is_self_paid' => (bool) $item->is_self_paid,
                    'toppings' => $item->relationLoaded('toppings') ? $item->toppings->map(static fn ($topping): array => [
                        'name' => $topping->topping_name,
                        'unit_price' => (int) $topping->unit_price,
                        'subtotal' => (int) $topping->subtotal,
                    ])->values()->all() : [],
                ])->values()->all(),
            ),
            'children' => $this->when(
                $this->resource->relationLoaded('children'),
                fn (): array => self::collection($this->resource->children)->resolve($request),
            ),
            'campaign' => $this->when(
                $this->resource->relationLoaded('campaign'),
                fn (): CampaignResource => new CampaignResource($this->resource->campaign),
            ),
            'room' => $this->when(
                $this->resource->relationLoaded('room'),
                fn (): RoomResource => new RoomResource($this->resource->room),
            ),
        ];
    }
}
