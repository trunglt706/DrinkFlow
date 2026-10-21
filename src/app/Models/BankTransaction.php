<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankProvider;
use App\Enums\BankTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An incoming (or outgoing) bank transaction reported by a room's bank feed, and how it was reconciled.
 */
class BankTransaction extends Model
{
    /** Incoming transfer. */
    public const DIRECTION_IN = 'in';

    /** Outgoing transfer (never reconciled). */
    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'room_id', 'provider', 'provider_transaction_id', 'account_number', 'direction', 'amount', 'content', 'reference_code',
        'transacted_at', 'status', 'reason', 'debt_id', 'payment_request_id', 'room_user_id', 'resolved_by_admin_id', 'resolved_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'provider' => BankProvider::class,
            'status' => BankTransactionStatus::class,
            'amount' => 'integer',
            'transacted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(Debt::class, 'payment_request_id')->withoutGlobalScope(Scopes\CampaignDebtScope::class);
    }

    public function roomUser(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }
}
