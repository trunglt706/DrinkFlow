<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\AdminInvoice;

/**
 * What an Agent still has to pay before / while using the admin area.
 *
 * A self-registered Agent has no package until its first invoice is paid: until then the admin area
 * is limited to its billing pages ({@see \App\Http\Middleware\EnsureFirstPaymentSettled}). An Agent
 * already using the platform is only reminded of its unpaid invoices (bottom banner of the admin layout).
 */
class PaymentRequirementService
{
    /**
     * Whether the Agent is waiting for its first payment (pending subscription and no active one).
     *
     * @param Admin $admin Agent.
     * @return bool True while the first invoice of a self-registration is unpaid.
     */
    public function awaitingFirstPayment(Admin $admin): bool
    {
        return $admin->subscriptions()->pending()->exists() && ! $admin->subscriptions()->active()->exists();
    }

    /**
     * Unpaid invoices to remind the Agent of: the most urgent one, their count and the amount still due.
     *
     * Overdue invoices come first, then the earliest due date.
     *
     * @param Admin $admin Agent.
     * @return array{invoice: AdminInvoice, count: int, due: int, overdue: bool}|null Null when nothing is owed.
     */
    public function reminder(Admin $admin): ?array
    {
        $invoices = $admin->invoices()->outstanding()
            ->whereColumn('paid_amount', '<', 'total')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [InvoiceStatus::Overdue->value])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
        if ($invoices->isEmpty()) {
            return null;
        }

        /** @var AdminInvoice $first */
        $first = $invoices->first();

        return [
            'invoice' => $first,
            'count' => $invoices->count(),
            'due' => (int) $invoices->sum(static fn (AdminInvoice $invoice): int => $invoice->remaining()),
            'overdue' => $first->status === InvoiceStatus::Overdue,
        ];
    }
}
