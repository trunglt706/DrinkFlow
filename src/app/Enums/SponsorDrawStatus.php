<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a sponsor draw session. The access link has its own expiry (link_expires_at): an expired
 * link blocks every viewer and every draw, but never changes this status nor deletes the results.
 */
enum SponsorDrawStatus: string
{
    /** Created from the list and not configured yet: no eligible snapshot, seed or link. */
    case Draft = 'draft';
    /** Snapshot frozen and seed committed; no turn drawn yet. */
    case Ready = 'ready';
    /** At least one turn drawn. */
    case Drawing = 'drawing';
    /** Target number of winners reached; waiting for the admin's confirmation. */
    case Completed = 'completed';
    /** Confirmed by an admin; notifications not requested (no channel configured). */
    case Confirmed = 'confirmed';
    case NotificationPending = 'notification_pending';
    case Notified = 'notified';
    case NotificationFailed = 'notification_failed';
    case Cancelled = 'cancelled';

    /** Whether another turn may be drawn. */
    public function canDraw(): bool
    {
        return $this === self::Ready || $this === self::Drawing;
    }

    /** Whether the admin may still cancel the session (results not confirmed yet). */
    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Ready, self::Drawing, self::Completed], true);
    }

    /**
     * Whether the settings (type, number of winners, target, shops) may still change. A Ready session may only
     * be reconfigured before its first turn; SponsorDrawService checks that part.
     */
    public function canConfigure(): bool
    {
        return $this === self::Draft || $this === self::Ready;
    }

    /** Whether the results have been confirmed by an admin. */
    public function isConfirmed(): bool
    {
        return in_array($this, [self::Confirmed, self::NotificationPending, self::Notified, self::NotificationFailed], true);
    }

    /** Whether the drawing part is over, so the seed may be revealed. */
    public function drawingFinished(): bool
    {
        return $this === self::Completed || $this === self::Cancelled || $this->isConfirmed();
    }
}
