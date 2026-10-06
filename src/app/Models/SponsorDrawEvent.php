<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only, hash-chained event of a sponsor draw session (App\Services\SponsorDraw\SponsorDrawEventLog).
 * Rows are written once and never updated or deleted by the application.
 */
class SponsorDrawEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['sponsor_draw_session_id', 'sequence', 'event_uuid', 'type', 'payload', 'actor_admin_id', 'previous_hash', 'event_hash', 'created_at'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'payload' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Sponsor draw events are append-only.'));
        static::deleting(static fn (): never => throw new LogicException('Sponsor draw events are append-only.'));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SponsorDrawSession::class, 'sponsor_draw_session_id');
    }
}
