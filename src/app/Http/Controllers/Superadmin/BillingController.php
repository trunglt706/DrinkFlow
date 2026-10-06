<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\PlatformPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPlatformPaymentRequest;
use App\Http\Requests\VoidPlatformInvoiceRequest;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Superadmin;
use App\Services\Authorization\AgentScope;
use App\Services\Billing\PlatformBillingService;
use App\Services\Billing\PlatformRevenueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Support\Helpers\DateRangeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform finance (Superadmin): revenue dashboard, outstanding invoices, Agent billing, payments and voids.
 *
 * Invoices and payments are Agent-owned data: every list, total and action is limited by the
 * Agent scope of the permission used (`revenue.view`, `debt.view`, `debt.manage`).
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly AgentScope $scope,
        private readonly PlatformBillingService $billing,
    ) {}

    /**
     * Revenue dashboard: MRR, invoiced, collected, outstanding, trend and package split.
     *
     * @param Request $request Incoming request.
     * @param PlatformRevenueService $revenue Revenue figures.
     * @return View Dashboard.
     */
    public function revenue(Request $request, PlatformRevenueService $revenue): View
    {
        $actor = $this->actor($request);

        return view('superadmin.billing.revenue', [
            'summary' => $revenue->summary($actor),
            'trend' => $revenue->trend($actor),
            'byPackage' => $revenue->byPackage($actor),
        ]);
    }

    /**
     * Outstanding (open and overdue) invoices of the visible Agents.
     *
     * The Agent filter only narrows the already scoped query, so an ID outside the scope simply matches nothing.
     *
     * @param Request $request Query string: q, status (open|overdue), agent (Agent ID), date_from / date_to (due date, Y-m-d).
     * @return View Outstanding list.
     */
    public function outstanding(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = InvoiceStatus::tryFrom($request->string('status')->toString());
        $status = $status?->isOutstanding() ? $status : null;
        $agentId = $request->integer('agent') ?: null;
        $from = DateRangeHelper::parseCarbonSafe($request->string('date_from')->toString() ?: null)?->startOfDay();
        $to = DateRangeHelper::parseCarbonSafe($request->string('date_to')->toString() ?: null)?->endOfDay();

        $query = AdminInvoice::query()->with(['admin:id,name,email,company', 'package:id,name'])->outstanding();
        $this->scope->apply($query, $this->actor($request), Permission::DebtView, 'admin_invoices.admin_id');
        $totalDue = (int) (clone $query)->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due');
        $invoices = $query
            ->when($status !== null, static fn ($q) => $q->where('status', $status->value))
            ->when($agentId !== null, static fn ($q) => $q->where('admin_id', $agentId))
            ->when($from !== null, static fn ($q) => $q->where('due_at', '>=', $from))
            ->when($to !== null, static fn ($q) => $q->where('due_at', '<=', $to))
            ->when($search !== '', static fn ($q) => $q->where(static fn ($inner) => $inner->where('number', 'like', "%{$search}%")
                ->orWhereHas('admin', static fn ($admins) => $admins->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->orderBy('due_at')
            ->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
            ->withQueryString();

        return view('superadmin.billing.outstanding', [
            'invoices' => $invoices,
            'totalDue' => $totalDue,
            'filters' => [
                'search' => $search, 'status' => $status?->value ?? '', 'agent' => $agentId,
                'date_from' => $from?->toDateString() ?? '', 'date_to' => $to?->toDateString() ?? '',
            ],
        ]);
    }

    /**
     * Billing detail of one Agent: invoices, payments and balance.
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Agent.
     * @return View Agent billing page.
     */
    public function agent(Request $request, Admin $admin): View
    {
        $this->ensureVisible($request, $admin, Permission::DebtView);

        return view('superadmin.billing.agent', [
            'agent' => $admin,
            'invoices' => $admin->invoices()->with('package:id,name')->latest('period_start')->latest('id')->get(),
            'payments' => $admin->platformPayments()->with(['invoice:id,number', 'recordedBy:id,name'])->latest('paid_at')->get(),
            'balance' => (int) $admin->invoices()->outstanding()->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due'),
            'canManage' => $this->scope->allows($this->actor($request), Permission::DebtManage, $admin->id),
            'methods' => PlatformPaymentMethod::cases(),
        ]);
    }

    /**
     * Record a manual payment (full or partial) of an invoice.
     *
     * @param RecordPlatformPaymentRequest $request Validated payment.
     * @param AdminInvoice $invoice Invoice.
     * @return RedirectResponse Agent billing page.
     */
    public function recordPayment(RecordPlatformPaymentRequest $request, AdminInvoice $invoice): RedirectResponse
    {
        $this->ensureVisible($request, $invoice->admin, Permission::DebtManage);
        $data = $request->validated();
        $this->billing->recordPayment(
            $invoice,
            $this->actor($request),
            (int) $data['amount'],
            PlatformPaymentMethod::from($data['method']),
            $data['reference'] ?? null,
            isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            $data['note'] ?? null,
        );

        return redirect()->route('superadmin.billing.agent', $invoice->admin_id)->with('status', __('platform.billing.payment_recorded', ['number' => $invoice->number]));
    }

    /**
     * Void an unpaid invoice.
     *
     * @param VoidPlatformInvoiceRequest $request Validated reason.
     * @param AdminInvoice $invoice Invoice.
     * @return RedirectResponse Agent billing page.
     */
    public function void(VoidPlatformInvoiceRequest $request, AdminInvoice $invoice): RedirectResponse
    {
        $this->ensureVisible($request, $invoice->admin, Permission::DebtManage);
        $this->billing->void($invoice, (string) $request->validated('reason'));

        return redirect()->route('superadmin.billing.agent', $invoice->admin_id)->with('status', __('platform.billing.voided', ['number' => $invoice->number]));
    }

    /**
     * 403 unless the Agent is inside the Superadmin's scope for the permission.
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Agent.
     * @param Permission $permission Permission exercised.
     * @return void
     */
    private function ensureVisible(Request $request, Admin $admin, Permission $permission): void
    {
        abort_unless($this->scope->allows($this->actor($request), $permission, $admin->id), Response::HTTP_FORBIDDEN);
    }

    /**
     * Signed-in superadmin.
     *
     * @param Request $request Incoming request.
     * @return Superadmin Actor.
     */
    private function actor(Request $request): Superadmin
    {
        /** @var Superadmin $actor */
        $actor = $request->user('superadmin');

        return $actor;
    }
}
