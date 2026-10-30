<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scoring rules of an owner for a game. Edits never change a version: they add a new one (see GameRuleSetService).
 */
class GameRuleSet extends Model
{
    protected $fillable = ['game_id', 'owner_id', 'name', 'allow_negative_points', 'current_version_id'];

    protected function casts(): array
    {
        return ['allow_negative_points' => 'boolean'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(GlobalUser::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GameRuleVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(GameRuleVersion::class, 'current_version_id');
    }
}
