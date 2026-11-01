<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stable identity of a player in a game room. Results reference the id, so renaming never rewrites history and an
 * inactive player keeps their past results.
 */
class GamePlayer extends Model
{
    protected $fillable = ['game_room_id', 'display_name', 'is_active', 'sort_order'];

    /** The claim token identifies a visitor's session: never serialise it. */
    protected $hidden = ['claimed_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'claimed_at' => 'datetime'];
    }

    public function gameRoom(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class);
    }
}
