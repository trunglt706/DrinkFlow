<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Superadmin\ReviewAgentRegistrationAction;
use App\Enums\AdminStatus;
use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Enums\SuperadminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveAgentRegistrationRequest;
use App\Http\Requests\RejectAgentRegistrationRequest;
use App\Models\Admin;
use App\Models\Package;
use App\Models\Superadmin;
use App\Services\Authorization\AgentScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pending Agent registrations (Agents → Pending Registrations): review, approve, reject.
 *
 * Pending Agents are not assigned to anyone yet, so the queue is shown to every holder of
 * `agent.approve`; a `managed` approver becomes the manager of the Agent they approve.
 */
class AgentRegistrationController extends Controller
{
    public function __construct(private readonly ReviewAgentRegistrationAction $action) {}

    /**
     * List registrations waiting for review.
     *
     * @param Request $request Query string: q, verification (verified|unverified).
     * @return View Registration queue.
     */
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $verification = $request->string('verification')->toString();
        $registrations = Admin::query()
            ->with('requestedPackage')
            ->where('status', AdminStatus::Pending->value)
            // Invited Agents carry `invited_at` and activate themselves from the invitation email,
            // so they never enter the self-service review queue.
            ->whereNotNull('registered_at')
            ->whereNull('invited_at')
            ->when($search !== '', static fn ($query) => $query->where(static fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")))
            ->when($verification === 'verified', static fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($verification === 'unverified', static fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy('registered_at')
            ->orderBy('id')
            ->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
            ->withQueryString();

        return view('superadmin.registrations.index', [
            'registrations' => $registrations,
            'filters' => ['search' => $search, 'verification' => in_array($verification, ['verified', 'unverified'], true) ? $verification : ''],
        ]);
    }

    /**
     * Review page of one registration.
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Registering Agent.
     * @param AgentScope $scope Superadmin scope (billing link only for those who may open it).
     * @return View Review page.
     */
    public function show(Request $request, Admin $admin, AgentScope $scope): View
    {
        abort_unless($admin->registered_at !== null, 404);
        $actor = $this->actor($request);
        // Once reviewed the Agent leaves the shared queue: only Superadmins who can see the Agent may open it.
        if ($admin->status !== AdminStatus::Pending) {
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('view', $admin);
        }

        // Pay-to-activate registration: its first invoice replaces the approval form.
        $firstInvoice = ReviewAgentRegistrationAction::awaitsPayment($admin)
            ? $admin->invoices()->where('type', InvoiceType::Subscription->value)->oldest('id')->first()
            : null;

        return view('superadmin.registrations.show', [
            'registration' => $admin->load(['requestedPackage', 'reviewedBy']),
            'firstInvoice' => $firstInvoice,
            'canViewBilling' => $scope->allows($actor, Permission::DebtView, $admin->id),
            'packages' => Package::query()->selectable()->get(),
            'managers' => Superadmin::query()->where('status', SuperadminStatus::Active->value)->orderBy('name')->get(['id', 'name', 'email']),
            'managedApprover' => $actor->scopeFor(Permission::AgentApprove) === PermissionScope::Managed,
        ]);
    }

    /**
     * Approve the registration and start its subscription.
     *
     * @param ApproveAgentRegistrationRequest $request Validated approval.
     * @param Admin $admin Pending Agent.
     * @return RedirectResponse Registration queue.
     */
    public function approve(ApproveAgentRegistrationRequest $request, Admin $admin): RedirectResponse
    {
        $managerId = $request->validated('manager_superadmin_id');
        $this->action->approve(
            $admin,
            $this->actor($request),
            $request->validated('package_id') !== null ? (int) $request->validated('package_id') : null,
            $managerId !== null ? Superadmin::query()->find((int) $managerId) : null,
        );

        return redirect()->route('superadmin.registrations.index')->with('status', __('platform.registrations.approved', ['name' => $admin->name]));
    }

    /**
     * Reject the registration.
     *
     * @param RejectAgentRegistrationRequest $request Validated reason.
     * @param Admin $admin Pending Agent.
     * @return RedirectResponse Registration queue.
     */
    public function reject(RejectAgentRegistrationRequest $request, Admin $admin): RedirectResponse
    {
        $this->action->reject($admin, $this->actor($request), (string) $request->validated('reason'));

        return redirect()->route('superadmin.registrations.index')->with('status', __('platform.registrations.rejected', ['name' => $admin->name]));
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
