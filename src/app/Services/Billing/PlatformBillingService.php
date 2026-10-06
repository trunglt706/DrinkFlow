<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PlatformPaymentMethod;
use App\Mail\SubscriptionStartedMail;
use App\Models\AdminInvoice;
use App\Models\AdminPayment;
use App\Models\AdminSubscription;
use App\Models\Superadmin;
use App\Services\Audit\AuditService;
use App\Services\Billing\Gateway\GatewayNotification;
use App\Enums\InvoiceType;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionUpgradeService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Platform billing of Agent subscriptions: monthly invoices, payments, overdue processing and voids.
 *
 * Invoices bill the subscription's `price_snapshot` (minus the upgrade credit on its first period),
 * never the live package price. Generation is idempotent: one invoice per subscription period,
 * guarded by the (admin_subscription_id, period_start) unique index, so re-running the scheduler or
 * running it concurrently never bills twice. This domain is separate from room debts.
 */
class PlatformBillingService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly BillingEnforcementService $enforcement,
        private readonly SubscriptionUpgradeService $upgrades,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Invoice the current period of every active subscription that has none yet.
     *
     * @param CarbonInterface|null $now Reference time (now by default).
     * @return int Number of invoices created.
     */
    public function generateInvoices(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $created = 0;
        AdminSubscription::query()->active()->whereNotNull('expires_at')->where('starts_at', '<=', $now)->orderBy('id')
            ->chunkById(200, function ($subscriptions) use ($now, &$created): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->invoiceCurrentPeriod($subscription, $now) !== null) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    /**
     * Invoice the period of the subscription that contains the reference time, unless already invoiced.
     *
     * @param AdminSubscription $subscription Active subscription.
     * @param CarbonInterface|null $now Reference time.
     * @return AdminInvoice|null Created invoice, or null when the period was already invoiced.
     */
    public function invoiceCurrentPeriod(AdminSubscription $subscription, ?CarbonInterface $now = null): ?AdminInvoice
    {
        $now ??= now();
        $periodEnd = $subscription->expires_at->copy();
        $periodStart = $periodEnd->copy()->subMonthsNoOverflow($subscription->periodMonths());
        if ($periodStart->lessThan($subscription->starts_at)) {
            $periodStart = $subscription->starts_at->copy();
        }
        // Any invoice of the period counts: a paid upgrade invoice is moved onto the new subscription as its first period.
        if (AdminInvoice::query()->where('admin_subscription_id', $subscription->id)->where('period_start', $periodStart)->exists()) {
            return null;
        }

        $firstPeriod = $periodStart->equalTo($subscription->starts_at);
        $periodPrice = $subscription->periodPrice();
        $credit = $firstPeriod ? min($subscription->proration_credit, $periodPrice) : 0;
        $total = $periodPrice - $credit;

        try {
            return DB::transaction(function () use ($subscription, $periodStart, $periodEnd, $periodPrice, $credit, $total, $now): AdminInvoice {
                $invoice = AdminInvoice::create([
                    'admin_id' => $subscription->admin_id,
                    'admin_subscription_id' => $subscription->id,
                    'package_id' => $subscription->package_id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'subtotal' => $periodPrice,
                    'credit' => $credit,
                    'total' => $total,
                    'paid_amount' => 0,
                    // A free period (free plan or fully credited upgrade) is settled right away.
                    'status' => $total === 0 ? InvoiceStatus::Paid->value : InvoiceStatus::Open->value,
                    'issued_at' => $now,
                    'due_at' => $now->copy()->addDays((int) config('platform.billing.due_days', 7)),
                    'paid_at' => $total === 0 ? $now : null,
                ]);
                $invoice->update(['number' => sprintf('INV-%s-%06d', $periodStart->format('Ym'), $invoice->id)]);
                $this->audit->record('invoice.issued', 'admin_invoice', $invoice->id, null, [], [
                    'admin_id' => $invoice->admin_id,
                    'number' => $invoice->number,
                    'total' => $invoice->total,
                    'period_start' => $periodStart->toIso8601String(),
                ]);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent run created the same period's invoice first.
            return null;
        }
    }

    /**
     * Mark open period invoices past their due date as overdue; unpaid upgrade requests past their due
     * date are voided instead (the Agent simply keeps its current package).
     *
     * @param CarbonInterface|null $now Reference time.
     * @return int Number of invoices that became overdue.
     */
    public function processOverdue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        // An unpaid upgrade request is not a debt: it expires instead of becoming overdue (no suspension).
        $this->upgrades->expireUnpaid($now);
        $count = 0;
        $ids = AdminInvoice::query()->where('status', InvoiceStatus::Open->value)->where('type', '!=', InvoiceType::Upgrade->value)->where('due_at', '<', $now)->pluck('id');
        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id, $now): bool {
                $invoice = AdminInvoice::query()->lockForUpdate()->find($id);
                if ($invoice === null || $invoice->status !== InvoiceStatus::Open || ! $invoice->due_at->lessThan($now)) {
                    return false;
                }
                $invoice->update(['status' => InvoiceStatus::Overdue->value, 'overdue_at' => $now]);
                $this->audit->record('invoice.overdue', 'admin_invoice', $invoice->id, null, ['status' => InvoiceStatus::Open->value], [
                    'status' => InvoiceStatus::Overdue->value,
                    'admin_id' => $invoice->admin_id,
                    'remaining' => $invoice->remaining(),
                ]);

                return true;
            });
            $count += $changed ? 1 : 0;
        }

        return $count;
    }

    /**
     * Record a (full or partial) payment of an outstanding invoice.
     *
     * Settling the last overdue invoice lifts an automatic billing suspension of the Agent.
     *
     * @param AdminInvoice $invoice Invoice.
     * @param Superadmin|null $actor Superadmin recording the payment (null for a payment-gateway notification).
     * @param int $amount Amount paid in VND (1..remaining).
     * @param PlatformPaymentMethod $method Payment method.
     * @param string|null $reference Bank reference or receipt number.
     * @param CarbonInterface|null $paidAt Payment time (now by default).
     * @param string|null $note Internal note.
     * @param string|null $gatewayTransactionId Transaction ID of the payment gateway (unique).
     * @return AdminPayment Recorded payment.
     * @throws ValidationException When the invoice is not outstanding or the amount is invalid.
     */
    /**
     * Issue the first invoice of a pending (self-registered) subscription: one full billing period.
     *
     * The period dates are provisional (from now); they move to the payment date when the invoice is
     * settled. A free package is settled at once, which starts the subscription immediately.
     *
     * @param AdminSubscription $subscription Pending subscription.
     * @return AdminInvoice Issued invoice.
     */
    public function invoicePendingSubscription(AdminSubscription $subscription): AdminInvoice
    {
        return DB::transaction(function () use ($subscription): AdminInvoice {
            $now = now();
            $total = $subscription->periodPrice();
            $invoice = AdminInvoice::create([
                'admin_id' => $subscription->admin_id,
                'admin_subscription_id' => $subscription->id,
                'type' => InvoiceType::Subscription->value,
                'package_id' => $subscription->package_id,
                'period_start' => $now,
                'period_end' => $now->copy()->addMonthsNoOverflow($subscription->periodMonths()),
                'subtotal' => $total,
                'credit' => 0,
                'total' => $total,
                'paid_amount' => 0,
                'status' => $total === 0 ? InvoiceStatus::Paid->value : InvoiceStatus::Open->value,
                'issued_at' => $now,
                'due_at' => $now->copy()->addDays((int) config('platform.billing.due_days', 7)),
                'paid_at' => $total === 0 ? $now : null,
            ]);
            $invoice->update(['number' => sprintf('INV-%s-%06d', $now->format('Ym'), $invoice->id)]);
            $this->audit->record('invoice.issued', 'admin_invoice', $invoice->id, null, [], [
                'admin_id' => $invoice->admin_id,
                'number' => $invoice->number,
                'total' => $invoice->total,
                'period_months' => $subscription->periodMonths(),
                'reason' => 'registration',
            ]);
            if ($total === 0) {
                $this->startPendingSubscription($invoice, $now);
            }

            return $invoice->refresh();
        });
    }

    /**
     * Start a self-registered Agent's subscription once its first invoice is settled.
     *
     * The first period runs from the payment, so the invoice is moved onto the real period, and the
     * Agent is told by email that the package is now usable. A no-op for any other invoice.
     *
     * @param AdminInvoice $invoice Settled subscription invoice (locked by the caller).
     * @param CarbonInterface $paidAt Payment time.
     * @return void
     */
    private function startPendingSubscription(AdminInvoice $invoice, CarbonInterface $paidAt): void
    {
        $subscription = AdminSubscription::query()->find($invoice->admin_subscription_id);
        if ($subscription === null || $subscription->status !== \App\Enums\SubscriptionStatus::Pending) {
            return;
        }
        $subscription = $this->subscriptions->activatePending($subscription, $paidAt);
        if ($subscription === null) {
            return;
        }
        $invoice->update(['period_start' => $subscription->starts_at, 'period_end' => $subscription->expires_at]);

        $admin = $invoice->admin()->first();
        if ($admin !== null) {
            Mail::to($admin->email)->queue(
                (new SubscriptionStartedMail($admin, $subscription->loadMissing('package')))->locale(app()->getLocale())->afterCommit(),
            );
        }
    }

    public function recordPayment(AdminInvoice $invoice, ?Superadmin $actor, int $amount, PlatformPaymentMethod $method, ?string $reference = null, ?CarbonInterface $paidAt = null, ?string $note = null, ?string $gatewayTransactionId = null): AdminPayment
    {
        $payment = DB::transaction(function () use ($invoice, $actor, $amount, $method, $reference, $paidAt, $note, $gatewayTransactionId): AdminPayment {
            $invoice = AdminInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->status->isOutstanding()) {
                throw ValidationException::withMessages(['invoice' => __('platform.billing.not_outstanding')]);
            }
            $remaining = $invoice->remaining();
            if ($amount < 1 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => __('platform.billing.amount_range', ['max' => $remaining])]);
            }

            $paidAt ??= now();
            $payment = AdminPayment::create([
                'admin_invoice_id' => $invoice->id,
                'admin_id' => $invoice->admin_id,
                'amount' => $amount,
                'method' => $method->value,
                'reference' => $reference,
                'gateway_transaction_id' => $gatewayTransactionId,
                'paid_at' => $paidAt,
                'recorded_by_superadmin_id' => $actor?->id,
                'note' => $note,
            ]);
            $before = ['status' => $invoice->status->value, 'paid_amount' => $invoice->paid_amount];
            $paidAmount = $invoice->paid_amount + $amount;
            $settled = $paidAmount >= $invoice->total;
            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $settled ? InvoiceStatus::Paid->value : $invoice->status->value,
                'paid_at' => $settled ? $paidAt : null,
            ]);
            $this->audit->record('invoice.payment_recorded', 'admin_invoice', $invoice->id, null, $before, [
                'status' => $invoice->status->value,
                'paid_amount' => $paidAmount,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'method' => $method->value,
            ]);
            if ($settled && $invoice->isUpgrade()) {
                // The upgrade was waiting for this payment: start the new package in the same transaction.
                $this->upgrades->applyPaid($invoice, $paidAt);
            } elseif ($settled) {
                $this->startPendingSubscription($invoice, $paidAt);
            }

            return $payment;
        });
        $this->enforcement->reactivateIfSettled($payment->admin);

        return $payment;
    }

    /**
     * Record a payment confirmed by the payment gateway (idempotent per transaction).
     *
     * A repeated notification returns the payment already recorded; notifications for unknown or
     * settled invoices are ignored. An amount above the remaining balance is capped to it.
     *
     * @param GatewayNotification $notification Verified notification.
     * @return AdminPayment|null Recorded (or previously recorded) payment, null when ignored.
     */
    public function recordGatewayPayment(GatewayNotification $notification): ?AdminPayment
    {
        if (! $notification->isPaid()) {
            return null;
        }
        $existing = AdminPayment::query()->where('gateway_transaction_id', $notification->transactionId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $invoice = AdminInvoice::query()->where('number', $notification->invoiceNumber)->first();
        $amount = $invoice !== null ? min($notification->amount, $invoice->remaining()) : 0;
        if ($invoice === null || $amount < 1) {
            $this->audit->record('invoice.gateway_payment_ignored', 'admin_invoice', (int) $invoice?->id, null, [], [
                'invoice_number' => $notification->invoiceNumber,
                'transaction_id' => $notification->transactionId,
                'amount' => $notification->amount,
            ]);

            return null;
        }

        try {
            return $this->recordPayment($invoice, null, $amount, PlatformPaymentMethod::Online, $notification->transactionId, null, null, $notification->transactionId);
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same notification recorded it first.
            return AdminPayment::query()->where('gateway_transaction_id', $notification->transactionId)->first();
        }
    }

    /**
     * Void an unpaid invoice (not owed anymore).
     *
     * @param AdminInvoice $invoice Invoice.
     * @param string $reason Reason kept on the invoice.
     * @return AdminInvoice Voided invoice.
     * @throws ValidationException When the invoice is settled or already has payments.
     */
    public function void(AdminInvoice $invoice, string $reason): AdminInvoice
    {
        $voided = DB::transaction(function () use ($invoice, $reason): AdminInvoice {
            $invoice = AdminInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->status->isOutstanding() || $invoice->paid_amount > 0) {
                throw ValidationException::withMessages(['invoice' => __('platform.billing.cannot_void')]);
            }
            $before = $invoice->status->value;
            $invoice->update(['status' => InvoiceStatus::Void->value, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->audit->record('invoice.voided', 'admin_invoice', $invoice->id, null, ['status' => $before], ['status' => InvoiceStatus::Void->value, 'reason' => $reason]);

            return $invoice;
        });
        $this->enforcement->reactivateIfSettled($voided->admin);

        return $voided;
    }
}
