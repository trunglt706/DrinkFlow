<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomFundTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry of a room's fund ledger. Entries are append-only: a mistake is corrected with an adjustment entry, never
 * by editing or deleting the original. Written only through RoomFundService, which keeps `balance_after` consistent.
 */
class RoomFundTransaction extends Model
{
    protected $fillable = ['room_id', 'type', 'amount', 'balance_after', 'room_user_id', 'debt_id', 'debt_payment_id', 'note', 'created_by_admin_id'];

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Room fund transactions are append-only.'));
        static::deleting(static fn (): never => throw new LogicException('Room fund transactions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => RoomFundTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomUser(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
