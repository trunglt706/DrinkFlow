<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySponsorStatistic extends Model
{
    protected $fillable = ['room_id', 'room_user_id', 'month', 'total_sponsored', 'sponsored_campaigns'];

    protected function casts(): array
    {
        return ['total_sponsored' => 'integer', 'sponsored_campaigns' => 'integer'];
    }

    public function roomUser(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class);
    }
}
