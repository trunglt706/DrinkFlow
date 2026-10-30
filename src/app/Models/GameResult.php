<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One winner against one or more losers, with a snapshot of the rule item applied. Never deleted: voided instead.
 */
class GameResult extends Model
{
    protected $fillable = [
        'game_room_id', 'game_round_id', 'winner_player_id', 'game_rule_item_id', 'game_rule_version_id',
        'rule_item_name', 'points', 'rule_version_number', 'idempotency_key', 'status', 'recorded_by',
        'recorded_by_player_id', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => GameResultStatus::class,
            'points' => 'integer',
            'rule_version_number' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function gameRoom(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'winner_player_id');
    }

    public function losers(): BelongsToMany
    {
        return $this->belongsToMany(GamePlayer::class, 'game_result_losers', 'game_result_id', 'game_player_id');
    }

    /**
     * Player who recorded the result from the public link (null when the owner recorded it).
     *
     * @return BelongsTo<GamePlayer, $this>
     */
    public function recordedByPlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'recorded_by_player_id');
    }

    public function scoreTransactions(): HasMany
    {
        return $this->hasMany(GameScoreTransaction::class);
    }

    /**
     * Whether the result has been voided.
     *
     * @return bool True when voided.
     */
    public function isVoided(): bool
    {
        return $this->status === GameResultStatus::Voided;
    }
}
