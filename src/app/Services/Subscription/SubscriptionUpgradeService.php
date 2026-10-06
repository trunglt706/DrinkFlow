<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Package;
use App\Services\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Package upgrades requested by an Agent: billed first, applied only once paid.
 *
 * Requesting an upgrade issues an `upgrade` invoice (new package price minus the unused part of the
 * current period). The Agent keeps the current package until that invoice is fully paid — a payment
 * recorded by a Superadmin or confirmed by the payment gateway — and then the new subscription starts.
 * The paid invoice becomes the first-period invoice of that subscription, so the period is never billed
 * twice. An unpaid request can be cancelled by the Agent and expires at its due date.
 */
class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Open upgrade request of the Agent (an outstanding upgrade invoice), if any.
     *
     * @param Admin $admin Agent.
     * @return AdminInvoice|null Pending upgrade invoice with its package.
     */
    public function pending(Admin $admin): ?AdminInvoice
    {
        return AdminInvoice::query()
            ->with('package')
            ->where('admin_id', $admin->id)
            ->where('type', InvoiceType::Upgrade->value)
            ->outstanding()
            ->latest('id')
            ->first();
    }

    /**
     * Issue the upgrade invoice for a bigger package; a free upgrade is applied right away.
     *
     * An earlier unpaid request is replaced (voided); one that already received a payment blocks a new one.
     *
     * @param Admin $admin Agent.
     * @param Package $package Target package.
     * @return AdminInvoice Upgrade invoice (already paid when its total is 0).
     * @throws ValidationException When there is no subscription, the package is unavailable or not an
     *                             upgrade, or a partly paid upgrade request is still open.
     */
    public function request(Admin $admin, Package $package): AdminInvoice
    {
        return DB::transaction(function () use ($admin, $package): AdminInvoice {
            $admin = Admin::query()->lockForUpdate()->findOrFail($admin->id);
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);
            if (! $package->status->isSelectable()) {
                throw ValidationException::withMessages(['package_id' => __('platform.subscriptions.package_unavailable')]);
            }
            $current = $this->subscriptions->current($admin);
            if ($current === null) {
                throw ValidationException::withMessages(['package_id' => __('platform.subscriptions.no_subscription')]);
            }
            if (! $this->subscriptions->isUpgrade($current, $package)) {
                throw ValidationException::withMessages(['package_id' => __('platform.subscriptions.not_an_upgrade')]);
            }

            $previous = AdminInvoice::query()->lockForUpdate()
                ->where('admin_id', $admin->id)
                ->where('type', InvoiceType::Upgrade->value)
                ->outstanding()
                ->first();
            if ($previous !== null) {
                if ($previous->paid_amount > 0) {
                    throw ValidationException::withMessages(['package_id' => __('platform.subscriptions.upgrade_in_progress', ['number' => $previous->number])]);
                }
                $this->voidRequest($previous, __('platform.subscriptions.upgrade_replaced'));
            }

            $now = now();
            // The new package keeps the current billing cycle: one full period of it, minus the unused value of the current one.
            $periodPrice = $package->monthly_price * $current->periodMonths();
            $credit = min($this->subscriptions->unusedCredit($current), $periodPrice);
            $total = $periodPrice - $credit;
            $invoice = AdminInvoice::create([
                'admin_id' => $admin->id,
                'admin_subscription_id' => $current->id,
                'type' => InvoiceType::Upgrade->value,
                'package_id' => $package->id,
                'period_start' => $now,
                'period_end' => $now->copy()->addMonthsNoOverflow($current->periodMonths()),
                'subtotal' => $periodPrice,
                'credit' => $credit,
                'total' => $total,
                'paid_amount' => 0,
                'status' => InvoiceStatus::Open->value,
                'issued_at' => $now,
                'due_at' => $now->copy()->addDays((int) config('platform.billing.due_days', 7)),
            ]);
            $invoice->update(['number' => sprintf('UPG-%s-%06d', $now->format('Ym'), $invoice->id)]);
            $this->audit->record('subscription.upgrade_requested', 'admin', $admin->id, null, ['package_id' => $current->package_id], [
                'package_id' => $package->id,
                'invoice_id' => $invoice->id,
                'total' => $total,
            ]);

            if ($total === 0) {
                // Fully covered by the unused credit: nothing to pay, apply it now.
                $invoice->update(['status' => InvoiceStatus::Paid->value, 'paid_at' => $now]);
                $this->applyPaid($invoice, $now);
            }

            return $invoice->refresh();
        });
    }

    /**
     * Start the new package once its upgrade invoice is fully paid.
     *
     * Must run inside the transaction that settled the invoice (PlatformBillingService::recordPayment).
     * The invoice is moved onto the new subscription as its first-period invoice.
     *
     * @param AdminInvoice $invoice Settled upgrade invoice, locked by the caller.
     * @param CarbonInterface|null $paidAt When the payment completed (now by default).
     * @return void
     */
    public function applyPaid(AdminInvoice $invoice, ?CarbonInterface $paidAt = null): void
    {
        if (! $invoice->isUpgrade() || $invoice->status !== InvoiceStatus::Paid || $invoice->package_id === null) {
            return;
        }
        $admin = Admin::query()->lockForUpdate()->findOrFail($invoice->admin_id);
        $package = Package::query()->findOrFail($invoice->package_id);
        $before = $this->subscriptions->current($admin);
        $startsAt = $paidAt ?? now();

        // The credit was fixed on the invoice when it was issued; keep it as the subscription's snapshot.
        $subscription = $this->subscriptions->activate($admin, $package, null, $startsAt, (int) $invoice->credit);
        $invoice->update([
            'admin_subscription_id' => $subscription->id,
            'period_start' => $subscription->starts_at,
            'period_end' => $subscription->expires_at,
        ]);
        $this->audit->record('subscription.upgraded', 'admin', $admin->id, null, [
            'subscription_id' => $before?->id,
            'package_id' => $before?->package_id,
        ], [
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Withdraw the Agent's unpaid upgrade request.
     *
     * @param Admin $admin Agent.
     * @return AdminInvoice|null The voided request, or null when there was none.
     * @throws ValidationException When the request already received a payment.
     */
    public function cancel(Admin $admin): ?AdminInvoice
    {
        return DB::transaction(function () use ($admin): ?AdminInvoice {
            $invoice = AdminInvoice::query()->lockForUpdate()
                ->where('admin_id', $admin->id)
                ->where('type', InvoiceType::Upgrade->value)
                ->outstanding()
                ->first();
            if ($invoice === null) {
                return null;
            }
            if ($invoice->paid_amount > 0) {
                throw ValidationException::withMessages(['invoice' => __('platform.billing.cannot_void')]);
            }

            return $this->voidRequest($invoice, __('platform.subscriptions.upgrade_cancelled_reason'));
        });
    }

    /**
     * Void unpaid upgrade requests past their due date (they never become overdue debts).
     *
     * @param CarbonInterface|null $now Reference time.
     * @return int Number of expired requests.
     */
    public function expireUnpaid(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $ids = AdminInvoice::query()
            ->where('type', InvoiceType::Upgrade->value)
            ->where('status', InvoiceStatus::Open->value)
            ->where('paid_amount', 0)
            ->where('due_at', '<', $now)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id, $now): int {
                $invoice = AdminInvoice::query()->lockForUpdate()->find($id);
                if ($invoice === null || $invoice->status !== InvoiceStatus::Open || $invoice->paid_amount > 0 || ! $invoice->due_at->lessThan($now)) {
                    return 0;
                }
                $this->voidRequest($invoice, __('platform.subscriptions.upgrade_expired'));

                return 1;
            });
        }

        return $count;
    }

    /**
     * Void one upgrade request with an audited reason.
     *
     * @param AdminInvoice $invoice Unpaid upgrade invoice (locked).
     * @param string $reason Reason kept on the invoice.
     * @return AdminInvoice Voided invoice.
     */
    private function voidRequest(AdminInvoice $invoice, string $reason): AdminInvoice
    {
        $before = $invoice->status->value;
        $invoice->update(['status' => InvoiceStatus::Void->value, 'voided_at' => now(), 'void_reason' => $reason]);
        $this->audit->record('invoice.voided', 'admin_invoice', $invoice->id, null, ['status' => $before], ['status' => InvoiceStatus::Void->value, 'reason' => $reason]);

        return $invoice;
    }
}
