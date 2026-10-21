<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Heart given by a member to a wish of an event wishes wall (at most one per member and wish).
 */
class RoomEventWishHeart extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['room_event_wish_id', 'room_user_id'];

    public function wish(): BelongsTo
    {
        return $this->belongsTo(RoomEventWish::class, 'room_event_wish_id');
    }
}
