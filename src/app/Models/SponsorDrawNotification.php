<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delivery of a confirmed sponsor draw result to one notification channel (Notify Gateway).
 */
class SponsorDrawNotification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sponsor_draw_session_id', 'notification_channel_id', 'channel_type', 'channel_name', 'status', 'payload_snapshot',
        'provider_reference', 'attempt_count', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['payload_snapshot' => 'array', 'attempt_count' => 'integer', 'sent_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SponsorDrawSession::class, 'sponsor_draw_session_id');
    }
}
