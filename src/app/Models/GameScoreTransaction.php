<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameScoreTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points ledger of a game room: a player's total is the sum of their transactions. Append-only.
 */
class GameScoreTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['game_room_id', 'game_result_id', 'game_player_id', 'type', 'points', 'reverses_transaction_id'];

    protected function casts(): array
    {
        return ['type' => GameScoreTransactionType::class, 'points' => 'integer'];
    }

    /**
     * Ledger entries are append-only: a correction is a reversal transaction, never an update or a delete.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new \LogicException('Game score transactions are append-only.'));
        static::deleting(static fn (): never => throw new \LogicException('Game score transactions are append-only.'));
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(GameResult::class, 'game_result_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
