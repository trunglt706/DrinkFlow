<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's rating of an item of their own order (see App\Services\Order\OrderItemRatingService).
 */
class OrderItemRating extends Model
{
    protected $fillable = ['room_id', 'order_item_id', 'room_user_id', 'restaurant_key', 'item_key', 'stars', 'comment', 'commented_at'];

    protected function casts(): array
    {
        return ['stars' => 'integer', 'commented_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function roomUser(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class);
    }
}
