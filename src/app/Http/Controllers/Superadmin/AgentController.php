<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Superadmin\AgentLifecycleAction;
use App\Actions\Superadmin\AssignAgentAction;
use App\Actions\Superadmin\InviteAgentAction;
use App\Enums\AdminStatus;
use App\Enums\Permission;
use App\Enums\SuperadminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentAssignmentRequest;
use App\Http\Requests\AgentStatusRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Package;
use App\Models\Superadmin;
use App\Services\Authorization\AgentDirectoryService;
use App\Services\Room\RoomQuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agent management (Agents → Agents): list, detail tabs, manager assignment, suspend/reactivate.
 *
 * The list uses Admin::visibleTo(); each tab and column follows its own permission scope
 * (AgentDirectoryService), so aggregates never leak data of Agents outside the scope.
 */
class AgentController extends Controller
{
    public function __construct(private readonly AgentDirectoryService $directory) {}

    /**
     * Agents visible to the Superadmin with package, quota, balance and primary manager.
     *
     * @param Request $request Query string: q, status, package.
     * @param RoomQuotaService $quota Room quota.
     * @return View Agent list.
     */
    public function index(Request $request, RoomQuotaService $quota): View
    {
        $actor = $this->actor($request);
        $search = trim($request->string('q')->toString());
        $status = AdminStatus::tryFrom($request->string('status')->toString());
        $packageId = $request->integer('package') ?: null;

        $agents = Admin::query()->visibleTo($actor)
            ->with(['activeSubscription.package:id,name', 'superadmins:id,name'])
            // Pending and rejected sign-ups live in the registration queue.
            ->when($status === null, static fn ($q) => $q->whereNotIn('status', [AdminStatus::Pending->value, AdminStatus::Rejected->value]))
            ->when($status !== null, static fn ($q) => $q->where('status', $status->value))
            ->when($packageId !== null, static fn ($q) => $q->whereHas('activeSubscription', static fn ($s) => $s->where('package_id', $packageId)))
            ->when($search !== '', static fn ($q) => $q->where(static fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
            ->withQueryString();

        $rows = $agents->getCollection();

        return view('superadmin.agents.index', [
            'agents' => $agents,
            'balances' => $this->directory->balances($actor, $rows),
            'usage' => $rows->mapWithKeys(fn (Admin $admin): array => [
                $admin->id => $this->directory->allows($actor, Permission::SubscriptionView, $admin) ? $quota->usage($admin) : null,
            ])->all(),
            'packages' => Package::query()->ordered()->get(['id', 'name']),
            'filters' => ['search' => $search, 'status' => $status?->value ?? '', 'package' => $packageId],
        ]);
    }

    /**
     * Agent creation form: the new Agent is pending until it activates its account.
     *
     * @return View Form.
     */
    public function create(): View
    {
        return view('superadmin.agents.create', [
            'packages' => Package::query()->selectable()->orderBy('sort_order')->orderBy('monthly_price')->get(),
        ]);
    }

    /**
     * Create the Agent and send its activation invitation.
     *
     * @param StoreAgentRequest $request Validated Agent data.
     * @param InviteAgentAction $action Invitation action.
     * @return RedirectResponse Agent page.
     */
    public function store(StoreAgentRequest $request, InviteAgentAction $action): RedirectResponse
    {
        $admin = $action->invite($request->validated(), $this->actor($request));

        return redirect()->route('superadmin.agents.show', $admin)->with('status', __('platform.agents.invited', ['name' => $admin->name]));
    }

    /**
     * Send a new activation link to an Agent that has not activated its account yet.
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Pending Agent.
     * @param InviteAgentAction $action Invitation action.
     * @return RedirectResponse Agent page.
     */
    public function resendActivation(Request $request, Admin $admin, InviteAgentAction $action): RedirectResponse
    {
        abort_unless($this->directory->allows($this->actor($request), Permission::AgentManage, $admin), Response::HTTP_FORBIDDEN);
        $sent = $action->resend($admin, $this->actor($request));

        return redirect()->route('superadmin.agents.show', $admin)->with(
            'status',
            $sent ? __('platform.agents.invitation_resent', ['name' => $admin->name]) : __('platform.agents.not_awaiting_activation'),
        );
    }

    /**
     * Agent page with one tab open.
     *
     * @param Request $request Query string: tab.
     * @param Admin $admin Agent.
     * @param RoomQuotaService $quota Room quota.
     * @return View Agent page.
     */
    public function show(Request $request, Admin $admin, RoomQuotaService $quota): View
    {
        $actor = $this->actor($request);
        abort_unless(Admin::query()->visibleTo($actor)->whereKey($admin->id)->exists(), Response::HTTP_FORBIDDEN);
        $tabs = $this->directory->tabsFor($actor, $admin);
        $tab = $request->string('tab', 'overview')->toString();
        abort_unless(in_array($tab, $tabs, true), array_key_exists($tab, AgentDirectoryService::TABS) ? Response::HTTP_FORBIDDEN : Response::HTTP_NOT_FOUND);

        $data = [
            'agent' => $admin->load(['superadmins:id,name,email', 'reviewedBy:id,name', 'requestedPackage:id,name']),
            'tabs' => $tabs,
            'tab' => $tab,
            'canManage' => $this->directory->allows($actor, Permission::AgentManage, $admin),
            'assignableSuperadmins' => Superadmin::query()->where('status', SuperadminStatus::Active->value)->orderBy('name')->get(['id', 'name', 'email']),
        ];

        return view('superadmin.agents.show', $data + match ($tab) {
            'subscription' => [
                'subscription' => $admin->activeSubscription()->with(['package', 'scheduledPackage'])->first(),
                'history' => $admin->subscriptions()->with('package')->latest('starts_at')->latest('id')->get(),
                'usage' => $quota->usage($admin),
            ],
            'rooms' => [
                'ownedRooms' => $admin->ownedRooms()->withCount(['roomUsers', 'campaigns'])->orderBy('name')->get(),
                'sharedRooms' => $admin->rooms()->where(static fn ($q) => $q->whereNull('owner_admin_id')->orWhere('owner_admin_id', '!=', $admin->id))->orderBy('name')->get(),
                'usage' => $quota->usage($admin),
            ],
            'campaigns' => [
                'campaigns' => Campaign::query()->with('room:id,name,slug')->whereIn('room_id', $admin->ownedRooms()->select('id'))->latest()->limit(50)->get(),
            ],
            'billing' => [
                'invoices' => $admin->invoices()->with('package:id,name')->latest('period_start')->latest('id')->limit(24)->get(),
                'balance' => (int) $admin->invoices()->outstanding()->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due'),
                'collected' => (int) $admin->platformPayments()->sum('amount'),
            ],
            'activity' => [
                'logs' => AuditLog::query()
                    ->where(static fn ($q) => $q->where(static fn ($t) => $t->where('target_type', 'admin')->where('target_id', $admin->id))
                        ->orWhere(static fn ($a) => $a->where('actor_type', AuditLog::ACTOR_ADMIN)->where('actor_id', $admin->id)))
                    ->with('actorSuperadmin:id,name')
                    ->latest('created_at')->latest('id')->limit(50)->get(),
            ],
            default => [
                'usage' => $this->directory->allows($actor, Permission::SubscriptionView, $admin) ? $quota->usage($admin) : null,
                'subscription' => $this->directory->allows($actor, Permission::SubscriptionView, $admin) ? $admin->activeSubscription()->with('package')->first() : null,
                'balance' => $this->directory->allows($actor, Permission::DebtView, $admin)
                    ? (int) $admin->invoices()->outstanding()->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due')
                    : null,
            ],
        });
    }

    /**
     * Assign the Agent to a Superadmin (optionally as primary manager).
     *
     * @param AgentAssignmentRequest $request Validated assignment.
     * @param Admin $admin Agent.
     * @param AssignAgentAction $action Assignment action.
     * @return RedirectResponse Agent page.
     */
    public function assign(AgentAssignmentRequest $request, Admin $admin, AssignAgentAction $action): RedirectResponse
    {
        $this->ensureCanManage($request, $admin);
        $manager = Superadmin::query()->findOrFail((int) $request->validated('superadmin_id'));
        $action->assign($manager, $admin, $this->actor($request), $request->boolean('is_primary'));

        return redirect()->route('superadmin.agents.show', $admin)->with('status', __('platform.agents.assigned', ['name' => $manager->name]));
    }

    /**
     * Remove the Agent from a Superadmin.
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Agent.
     * @param Superadmin $superadmin Manager to remove.
     * @param AssignAgentAction $action Assignment action.
     * @return RedirectResponse Agent page.
     */
    public function unassign(Request $request, Admin $admin, Superadmin $superadmin, AssignAgentAction $action): RedirectResponse
    {
        $this->ensureCanManage($request, $admin);
        $action->unassign($superadmin, $admin);

        return redirect()->route('superadmin.agents.show', $admin)->with('status', __('platform.agents.unassigned', ['name' => $superadmin->name]));
    }

    /**
     * Suspend the Agent (its rooms are blocked, the subscription is kept).
     *
     * @param AgentStatusRequest $request Validated reason.
     * @param Admin $admin Agent.
     * @param AgentLifecycleAction $action Lifecycle action.
     * @return RedirectResponse Agent page.
     */
    public function suspend(AgentStatusRequest $request, Admin $admin, AgentLifecycleAction $action): RedirectResponse
    {
        $this->ensureCanManage($request, $admin);
        $action->suspend($admin, (string) $request->validated('reason'));

        return redirect()->route('superadmin.agents.show', $admin)->with('status', __('platform.agents.suspended'));
    }

    /**
     * Reactivate a suspended Agent.
     *
     * @param AgentStatusRequest $request Validated note.
     * @param Admin $admin Agent.
     * @param AgentLifecycleAction $action Lifecycle action.
     * @return RedirectResponse Agent page.
     */
    public function reactivate(AgentStatusRequest $request, Admin $admin, AgentLifecycleAction $action): RedirectResponse
    {
        $this->ensureCanManage($request, $admin);
        $action->reactivate($admin, $request->validated('reason'));

        return redirect()->route('superadmin.agents.show', $admin)->with('status', __('platform.agents.reactivated'));
    }

    /**
     * 403 unless the Superadmin may manage this Agent (`agent.manage` within scope).
     *
     * @param Request $request Incoming request.
     * @param Admin $admin Agent.
     * @return void
     */
    private function ensureCanManage(Request $request, Admin $admin): void
    {
        abort_unless($this->directory->allows($this->actor($request), Permission::AgentManage, $admin), Response::HTTP_FORBIDDEN);
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
