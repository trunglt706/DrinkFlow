<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A game of the /me "Trò chơi" catalogue (seeded by Database\Seeders\GameSeeder).
 */
class Game extends Model
{
    /** Slug of the game seeded by default. */
    public const SLUG_FUN = 'game-vui';

    protected $fillable = ['slug', 'name', 'description', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * Games shown in the catalogue, in display order.
     *
     * @param Builder<Game> $query Query being built.
     * @return Builder<Game> Filtered query.
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function gameRooms(): HasMany
    {
        return $this->hasMany(GameRoom::class);
    }

    public function ruleSets(): HasMany
    {
        return $this->hasMany(GameRuleSet::class);
    }
}
