<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen eligibility snapshot row of a sponsor draw session.
 */
class SponsorDrawEligibleUser extends Model
{
    protected $fillable = ['sponsor_draw_session_id', 'room_user_id', 'position', 'display_name_snapshot', 'is_winner'];

    protected function casts(): array
    {
        return ['room_user_id' => 'integer', 'position' => 'integer', 'is_winner' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SponsorDrawSession::class, 'sponsor_draw_session_id');
    }
}
