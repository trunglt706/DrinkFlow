<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One drawn turn (winner) of a sponsor draw session.
 */
class SponsorDrawResult extends Model
{
    protected $fillable = ['sponsor_draw_session_id', 'room_user_id', 'snapshot_position', 'display_name_snapshot', 'draw_order', 'drawn_by_admin_id', 'drawn_at'];

    protected function casts(): array
    {
        return ['room_user_id' => 'integer', 'snapshot_position' => 'integer', 'draw_order' => 'integer', 'drawn_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SponsorDrawSession::class, 'sponsor_draw_session_id');
    }

    public function drawnBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'drawn_by_admin_id');
    }
}
