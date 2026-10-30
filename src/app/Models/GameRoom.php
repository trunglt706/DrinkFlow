<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameRoomStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A play session of a game owned by a global user. Anyone holding the public token may view it read-only.
 */
class GameRoom extends Model
{
    protected $fillable = [
        'game_id', 'owner_id', 'name', 'status', 'game_rule_set_id', 'game_rule_version_id', 'public_token',
        'started_at', 'finished_at', 'last_activity_at',
    ];

    /** The token is a capability: never serialize it by accident. */
    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'status' => GameRoomStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(GlobalUser::class, 'owner_id');
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(GameRuleSet::class, 'game_rule_set_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(GameRuleVersion::class, 'game_rule_version_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }

    public function scoreTransactions(): HasMany
    {
        return $this->hasMany(GameScoreTransaction::class);
    }

    /**
     * Public URL of the room, or null while the link is revoked.
     *
     * @return string|null Absolute URL.
     */
    public function publicUrl(): ?string
    {
        return $this->public_token === null ? null : route('public.games.rooms.show', $this->public_token);
    }
}
