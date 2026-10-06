<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Superadmin\ManageSuperadminAction;
use App\Enums\Permission;
use App\Enums\SuperadminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuperadminRequest;
use App\Http\Requests\SyncSuperadminPermissionsRequest;
use App\Http\Requests\UpdateSuperadminRequest;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Superadmin accounts and their permissions (Governance → Superadmins / Permissions).
 */
class SuperadminController extends Controller
{
    public function __construct(private readonly ManageSuperadminAction $action) {}

    /**
     * List Superadmins.
     *
     * @param Request $request Query string: q, status.
     * @return View Superadmin list.
     */
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = SuperadminStatus::tryFrom($request->string('status')->toString());
        $superadmins = Superadmin::query()
            ->withCount('permissions')
            ->when($search !== '', static fn ($query) => $query->where(static fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== null, static fn ($query) => $query->where('status', $status->value))
            ->orderBy('name')
            ->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
            ->withQueryString();

        return view('superadmin.superadmins.index', [
            'superadmins' => $superadmins,
            'filters' => ['search' => $search, 'status' => $status?->value ?? ''],
            'permissionTotal' => count(Permission::cases()),
        ]);
    }

    /**
     * Show one Superadmin with the permission matrix.
     *
     * @param Superadmin $superadmin Account.
     * @return View Detail page.
     */
    public function show(Superadmin $superadmin): View
    {
        $grants = $superadmin->permissions()->get()
            ->mapWithKeys(static fn (PermissionRecord $record): array => [$record->key => $record->pivot->scope->value])
            ->all();
        $groups = collect(Permission::cases())->groupBy(static fn (Permission $permission): string => $permission->group());

        return view('superadmin.superadmins.show', [
            'superadmin' => $superadmin,
            'grants' => $grants,
            'permissionGroups' => $groups,
        ]);
    }

    /**
     * Create a Superadmin, then open its page to grant permissions.
     *
     * @param StoreSuperadminRequest $request Validated account data.
     * @return RedirectResponse Detail page.
     */
    public function store(StoreSuperadminRequest $request): RedirectResponse
    {
        $superadmin = $this->action->create($request->validated());

        return redirect()->route('superadmin.superadmins.show', $superadmin)
            ->with('status', __('superadmin.superadmins.created'));
    }

    /**
     * Update profile, password and status.
     *
     * @param UpdateSuperadminRequest $request Validated data.
     * @param Superadmin $superadmin Account.
     * @return RedirectResponse Detail page.
     */
    public function update(UpdateSuperadminRequest $request, Superadmin $superadmin): RedirectResponse
    {
        $this->action->update($superadmin, $this->actor($request), $request->validated());

        return redirect()->route('superadmin.superadmins.show', $superadmin)->with('status', __('superadmin.superadmins.updated'));
    }

    /**
     * Replace the permissions and scopes.
     *
     * @param SyncSuperadminPermissionsRequest $request Validated grants.
     * @param Superadmin $superadmin Account.
     * @return RedirectResponse Detail page.
     */
    public function permissions(SyncSuperadminPermissionsRequest $request, Superadmin $superadmin): RedirectResponse
    {
        $this->action->syncPermissions($superadmin, $this->actor($request), $request->grants());

        return redirect()->route('superadmin.superadmins.show', $superadmin)->with('status', __('superadmin.superadmins.permissions_saved'));
    }

    /**
     * Load the permissions added to the code (App\Enums\Permission) into the database, like
     * `php artisan permissions:sync` without --prune: grants of every account stay untouched.
     *
     * @param Request $request Incoming request.
     * @param PermissionCatalogService $catalog Permission catalog.
     * @param AuditService $audit Audit trail.
     * @return RedirectResponse Back with the sync result.
     */
    public function syncCatalog(Request $request, PermissionCatalogService $catalog, AuditService $audit): RedirectResponse
    {
        $result = $catalog->sync();
        $audit->record('permissions.catalog_synced', 'permission', 0, null, [], ['added' => $result['added'], 'stale' => $result['stale']]);

        $status = $result['added'] === []
            ? __('superadmin.permissions.synced_none')
            : __('superadmin.permissions.synced_added', ['count' => count($result['added']), 'keys' => implode(', ', $result['added'])]);
        if ($result['stale'] !== []) {
            $status .= ' '.__('superadmin.permissions.synced_stale', ['count' => count($result['stale']), 'keys' => implode(', ', $result['stale'])]);
        }

        return back()->with('status', $status);
    }

    /**
     * Delete a Superadmin.
     *
     * @param Request $request Incoming request.
     * @param Superadmin $superadmin Account.
     * @return RedirectResponse Superadmin list.
     */
    public function destroy(Request $request, Superadmin $superadmin): RedirectResponse
    {
        $this->action->delete($superadmin, $this->actor($request));

        return redirect()->route('superadmin.superadmins.index')->with('status', __('superadmin.superadmins.deleted'));
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
