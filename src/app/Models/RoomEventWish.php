<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A wish written by a member on the wishes wall of a room event page (see App\Services\Occasion\RoomEventWishWallService).
 * `occurrence` is the date of the celebrated occurrence; a hidden wish (room admin) is no longer shown to members.
 */
class RoomEventWish extends Model
{
    protected $fillable = ['room_id', 'event_type', 'occurrence', 'room_user_id', 'recipient_room_user_id', 'message', 'hearts_count', 'hidden_at', 'hidden_by_admin_id'];

    protected function casts(): array
    {
        return [
            'occurrence' => 'date',
            'hearts_count' => 'integer',
            'hidden_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class, 'room_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class, 'recipient_room_user_id');
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(RoomEventWishHeart::class);
    }
}
