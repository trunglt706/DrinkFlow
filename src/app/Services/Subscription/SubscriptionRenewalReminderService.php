<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\AdminStatus;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\AdminSubscription;
use App\Services\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Daily "your package renews soon" emails.
 *
 * An active subscription whose period ends within `platform.billing.renewal_reminder_days` gets one
 * reminder per period (`renewal_reminder_sent_for` remembers the period end it was sent for). Nothing is
 * sent when the Agent asked to stop the renewal or is not active. When the period ends,
 * {@see SubscriptionService::processDuePeriods()} renews it and the billing job issues the unpaid
 * invoice of the new period; unpaid past the grace period, the Agent is suspended
 * ({@see \App\Services\Billing\BillingEnforcementService}).
 */
class SubscriptionRenewalReminderService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Send the renewal reminder of every subscription about to renew that was not reminded yet.
     *
     * @param CarbonInterface|null $now Reference time.
     * @return int Number of reminders queued.
     */
    public function remindExpiring(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $days = max(1, (int) config('platform.billing.renewal_reminder_days', 7));
        $sent = 0;

        AdminSubscription::query()->active()
            ->where('cancel_at_period_end', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays($days))
            ->whereHas('admin', static fn ($query) => $query->where('status', AdminStatus::Active->value))
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use (&$sent): void {
                foreach ($subscriptions as $subscription) {
                    $sent += $this->remind((int) $subscription->id) ? 1 : 0;
                }
            });

        return $sent;
    }

    /**
     * Send one reminder, unless it was already sent for the current period end.
     *
     * @param int $subscriptionId Subscription ID.
     * @return bool True when a reminder was queued.
     */
    private function remind(int $subscriptionId): bool
    {
        return DB::transaction(function () use ($subscriptionId): bool {
            $subscription = AdminSubscription::query()->lockForUpdate()->with(['admin', 'package'])->find($subscriptionId);
            if ($subscription === null || $subscription->expires_at === null || $subscription->admin === null
                || ($subscription->renewal_reminder_sent_for !== null && $subscription->renewal_reminder_sent_for->equalTo($subscription->expires_at))) {
                return false;
            }

            $subscription->update(['renewal_reminder_sent_for' => $subscription->expires_at]);
            $this->audit->record('subscription.renewal_reminded', 'admin', $subscription->admin_id, null, [], [
                'subscription_id' => $subscription->id,
                'expires_at' => $subscription->expires_at->toIso8601String(),
            ]);
            Mail::to($subscription->admin->email)->queue(
                (new SubscriptionRenewalReminderMail($subscription->admin, $subscription))->locale(app()->getLocale())->afterCommit(),
            );

            return true;
        });
    }
}
