<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round (turn) of a game room grouping the results recorded during it. New results go to the latest round.
 */
class GameRound extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['game_room_id', 'number'];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    public function gameRoom(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }
}
