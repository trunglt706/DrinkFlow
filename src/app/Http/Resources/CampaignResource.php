<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->resource->status;

        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'restaurant' => $this->resource->restaurant,
            'description' => $this->resource->description,
            'sponsor_name' => $this->resource->sponsor_name,
            'sponsor_type' => $this->resource->sponsor_type,
            'sponsor_description' => $this->resource->sponsor_description,
            'max_budget' => $this->resource->max_budget === null ? null : (int) $this->resource->max_budget,
            'flat_price' => $this->resource->flat_price === null ? null : (int) $this->resource->flat_price,
            'delivery_fee' => (int) ($this->resource->delivery_fee ?? 0),
            'discount' => (int) ($this->resource->discount ?? 0),
            'deadline' => $this->resource->deadline?->toISOString(),
            'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
            'items' => $this->when(
                $this->resource->relationLoaded('items'),
                fn (): array => $this->resource->items->map(static fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'description' => $item->description,
                    'image_url' => $item->image_url,
                    'base_price' => (int) $item->base_price,
                    'sponsor_amount' => (int) ($item->sponsor_amount ?? 0),
                    'sizes' => $item->relationLoaded('sizes') ? $item->sizes->map(static fn ($size): array => [
                        'id' => $size->id,
                        'name' => $size->name,
                        'price_delta' => (int) $size->price_delta,
                    ])->values()->all() : [],
                    'toppings' => $item->relationLoaded('toppings') ? $item->toppings->map(static fn ($topping): array => [
                        'id' => $topping->id,
                        'name' => $topping->name,
                        'price' => (int) $topping->price,
                    ])->values()->all() : [],
                ])->values()->all(),
            ),
            'room' => $this->when(
                $this->resource->relationLoaded('room'),
                fn (): RoomResource => new RoomResource($this->resource->room),
            ),
        ];
    }
}
