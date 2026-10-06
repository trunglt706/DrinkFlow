<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationChannel;
use App\Models\SponsorDrawNotification;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\SponsorDraw\SponsorDrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Deliver a confirmed sponsor draw result to one notification channel. A delivery already marked sent is
 * never sent again (idempotent against a double dispatch or a resend request).
 */
class SendSponsorDrawNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Failures are recorded on the delivery row and retried by the admin, not by the queue. */
    public int $tries = 1;

    /**
     * @param int $notificationId Delivery row ID.
     */
    public function __construct(public readonly int $notificationId) {}

    /**
     * @param RoomNotificationChannelDispatcher $dispatcher Notify Gateway.
     * @param SponsorDrawService $draws Records the outcome.
     */
    public function handle(RoomNotificationChannelDispatcher $dispatcher, SponsorDrawService $draws): void
    {
        // Claim the row: only a pending delivery is attempted, and only once at a time.
        $notification = DB::transaction(function (): ?SponsorDrawNotification {
            $row = SponsorDrawNotification::query()->lockForUpdate()->find($this->notificationId);
            if ($row === null || $row->status !== SponsorDrawNotification::STATUS_PENDING) {
                return null;
            }
            $row->update(['attempt_count' => $row->attempt_count + 1]);

            return $row;
        });
        if ($notification === null) {
            return;
        }

        $error = null;
        try {
            $channel = NotificationChannel::query()->where('status', NotificationChannel::STATUS_ENABLED)->find($notification->notification_channel_id);
            if ($channel === null) {
                throw new \RuntimeException('Notification channel is disabled or deleted.');
            }
            $dispatcher->sendTo($channel, (array) $notification->payload_snapshot);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage() !== '' ? $exception->getMessage() : $exception::class;
        }

        $draws->recordDelivery($notification->fresh(), $error);
    }
}
