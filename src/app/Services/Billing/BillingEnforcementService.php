<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Actions\Superadmin\AgentLifecycleAction;
use App\Enums\AdminStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Services\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Grace period and automatic suspension for unpaid platform invoices.
 *
 * An overdue invoice gives the Agent `grace_days` more days of full access. When automatic
 * suspension is enabled, Agents still owing an invoice after its grace period are suspended
 * (marked with `billing_suspended_at`); paying or voiding the invoices lifts that suspension
 * automatically. Suspensions decided by a Superadmin are never lifted here.
 */
class BillingEnforcementService
{
    public function __construct(
        private readonly AgentLifecycleAction $lifecycle,
        private readonly AuditService $audit,
        private readonly BillingSuspensionNotifier $notifier,
    ) {}

    /**
     * End of the grace period of an invoice.
     *
     * @param AdminInvoice $invoice Invoice.
     * @return CarbonInterface Due date plus the grace days.
     */
    public function graceEndsAt(AdminInvoice $invoice): CarbonInterface
    {
        return $invoice->due_at->copy()->addDays((int) config('platform.billing.grace_days', 7));
    }

    /**
     * Overdue invoice of the Agent whose grace period ends first, if any.
     *
     * @param Admin $admin Agent.
     * @return AdminInvoice|null Oldest overdue invoice.
     */
    public function oldestOverdue(Admin $admin): ?AdminInvoice
    {
        return $admin->invoices()->where('status', InvoiceStatus::Overdue->value)->orderBy('due_at')->first();
    }

    /**
     * Suspend active Agents whose overdue invoices are past the grace period (no-op when disabled).
     *
     * @param CarbonInterface|null $now Reference time.
     * @return int Number of Agents suspended.
     */
    public function suspendOverdueAgents(?CarbonInterface $now = null): int
    {
        if (! config('platform.billing.auto_suspend', false)) {
            return 0;
        }
        $now ??= now();
        $graceStart = $now->copy()->subDays((int) config('platform.billing.grace_days', 7));
        // Only invoices of a running package suspend: an unpaid registration invoice simply leaves the
        // package unstarted (there is no service to suspend yet).
        $adminIds = AdminInvoice::query()->where('status', InvoiceStatus::Overdue->value)->where('due_at', '<', $graceStart)
            ->whereHas('subscription', static fn ($query) => $query->where('status', '!=', SubscriptionStatus::Pending->value))
            ->distinct()->pluck('admin_id');

        $suspended = 0;
        foreach ($adminIds as $adminId) {
            $admin = Admin::query()->find($adminId);
            if ($admin === null || $admin->status !== AdminStatus::Active) {
                continue;
            }
            DB::transaction(function () use ($admin, $now): void {
                $invoice = $this->oldestOverdue($admin);
                $this->lifecycle->suspend($admin, __('platform.billing.auto_suspend_reason', ['number' => $invoice?->number]));
                $admin->forceFill(['billing_suspended_at' => $now])->save();
                $this->audit->record('agent.auto_suspended', 'admin', $admin->id, null, [], ['invoice_id' => $invoice?->id, 'invoice_number' => $invoice?->number]);
                // Email to the Agent + inbox/realtime/outbound alerts to its responsible Superadmins (after commit).
                $this->notifier->notifySuspended($admin, $invoice);
            });
            $suspended++;
        }

        return $suspended;
    }

    /**
     * Lift an automatic billing suspension once the Agent owes no overdue invoice anymore.
     *
     * @param Admin $admin Agent.
     * @return bool True when the Agent was reactivated.
     */
    public function reactivateIfSettled(Admin $admin): bool
    {
        $admin = $admin->fresh();
        if ($admin === null || $admin->billing_suspended_at === null || $admin->status !== AdminStatus::Suspended || $this->oldestOverdue($admin) !== null) {
            return false;
        }

        DB::transaction(function () use ($admin): void {
            $this->lifecycle->reactivate($admin, __('platform.billing.auto_reactivate_reason'));
            $admin->forceFill(['billing_suspended_at' => null])->save();
            $this->audit->record('agent.auto_reactivated', 'admin', $admin->id);
        });

        return true;
    }
}
