<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'avatar_url' => $this->resource->avatar_url,
            'campaigns_count' => $this->when(isset($this->resource->campaigns_count), (int) ($this->resource->campaigns_count ?? 0)),
        ];
    }
}
