<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->resource->status;

        return [
            'id' => $this->resource->id,
            'user_code' => $this->resource->user_code,
            'display_name' => $this->resource->display_name,
            'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
            'joined_at' => $this->resource->joined_at?->toISOString(),
            'room' => $this->when(
                $this->resource->relationLoaded('room'),
                fn (): RoomResource => new RoomResource($this->resource->room),
            ),
        ];
    }
}
