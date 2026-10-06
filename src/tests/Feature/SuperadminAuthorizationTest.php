<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\Superadmin;
use App\Services\Authorization\AgentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * T05: Gate per feature, policies per resource, and the all/managed Agent scope.
 */
class SuperadminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** Superadmin routes every active superadmin may use without a feature permission. */
    private const OPEN_ROUTES = [
        'superadmin.dashboard', 'superadmin.dashboard.analytics', 'superadmin.dashboard.insights', 'superadmin.dashboard.trends',
        'superadmin.admin-notifications.read-all', 'superadmin.admin-notifications.read', 'superadmin.socket-token',
        'superadmin.login.page', 'superadmin.login', 'superadmin.logout',
        // The internal handbook is part of the shell: every signed-in Superadmin may read it.
        'superadmin.guides.index', 'superadmin.guides.show',
        // Revealing a masked value needs a token issued to the same Superadmin by a page it could open.
        'superadmin.sensitive.reveal',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /**
     * Superadmin holding only the given permissions.
     *
     * @param array<string, PermissionScope> $grants Permission key => scope.
     * @return Superadmin Superadmin.
     */
    private function superadminWith(array $grants = []): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped-'.count($grants).'-'.uniqid().'@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }

        return $superadmin;
    }

    private function agentWithRoom(string $slug): Admin
    {
        $admin = Admin::create(['name' => 'Agent '.$slug, 'email' => "agent-{$slug}@drinkflow.test", 'password' => 'password123', 'status' => 'active']);
        $admin->rooms()->attach(Room::create(['name' => 'Room '.$slug, 'slug' => $slug, 'status' => 'active']));

        return $admin;
    }

    public function test_every_superadmin_route_requires_a_permission_except_the_shell(): void
    {
        $unguarded = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'superadmin.') || in_array($name, self::OPEN_ROUTES, true)) {
                continue;
            }
            $permissions = array_filter($route->gatherMiddleware(), static fn ($m): bool => is_string($m) && str_starts_with($m, 'permission:'));
            if ($permissions === []) {
                $unguarded[] = $name;
            }
            foreach ($permissions as $middleware) {
                $this->assertNotNull(Permission::tryFrom(substr($middleware, 11)), "{$name}: {$middleware}");
            }
        }
        $this->assertSame([], $unguarded);
    }

    public function test_a_gate_exists_for_every_permission(): void
    {
        $holder = $this->superadminWith([Permission::AuditView->value => PermissionScope::All]);
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(Gate::has($permission->value), $permission->value);
            $this->assertSame($permission === Permission::AuditView, Gate::forUser($holder)->allows($permission->value), $permission->value);
        }
        // An Admin never passes a superadmin gate.
        $admin = Admin::create(['name' => 'Agent', 'email' => 'gate-agent@drinkflow.test', 'password' => 'x', 'status' => 'active']);
        $this->assertFalse(Gate::forUser($admin)->allows(Permission::AuditView->value));
    }

    public function test_features_are_denied_without_permission_and_menu_follows_gates(): void
    {
        $superadmin = $this->superadminWith([Permission::AuditView->value => PermissionScope::All]);
        $this->actingAs($superadmin, 'superadmin');

        $this->get(route('superadmin.dashboard'))->assertOk()
            ->assertSee(route('superadmin.audit.page'), false)
            ->assertDontSee(route('superadmin.admins.page'), false)
            ->assertDontSee(route('superadmin.system.page'), false);
        $this->get(route('superadmin.audit.page'))->assertOk();
        $this->get(route('superadmin.admins.page'))->assertForbidden();
        $this->getJson(route('superadmin.system.index'))->assertForbidden();
        $this->putJson(route('superadmin.system.settings'), ['settings' => [['key' => 'x', 'value' => '1', 'type' => 'string']]])->assertForbidden();
        $this->assertDatabaseMissing('system_settings', ['key' => 'x']);
    }

    public function test_view_permission_does_not_allow_changes(): void
    {
        $agent = $this->agentWithRoom('view-only');
        $this->actingAs($this->superadminWith([Permission::AgentView->value => PermissionScope::All]), 'superadmin');

        $this->getJson(route('superadmin.admins.show', $agent))->assertOk();
        $this->patchJson(route('superadmin.admins.status', $agent), ['status' => 'suspended'])->assertForbidden();
        $this->deleteJson(route('superadmin.admins.destroy', $agent))->assertForbidden();
        $this->postJson(route('superadmin.admins.store'), ['name' => 'X', 'email' => 'x@drinkflow.test', 'password' => 'password123'])->assertForbidden();
        $this->assertSame('active', $agent->fresh()->getStatusValue());
    }

    public function test_managed_scope_only_reaches_assigned_agents(): void
    {
        $agent = $this->agentWithRoom('scoped-agent');
        $superadmin = $this->superadminWith([
            Permission::AgentView->value => PermissionScope::Managed,
            Permission::AgentManage->value => PermissionScope::Managed,
            Permission::RoomView->value => PermissionScope::Managed,
        ]);
        $this->actingAs($superadmin, 'superadmin');

        // No Agent is assigned yet, so the managed scope sees nothing.
        $this->getJson(route('superadmin.admins.index'))->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson(route('superadmin.admins.show', $agent))->assertForbidden();
        $this->patchJson(route('superadmin.admins.status', $agent), ['status' => 'suspended'])->assertForbidden();
        $this->getJson(route('superadmin.rooms.index'))->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson(route('superadmin.rooms.show', $agent->rooms()->first()))->assertForbidden();
        $this->assertSame([], app(AgentScope::class)->adminIds($superadmin, Permission::AgentView));
    }

    public function test_all_scope_reaches_every_agent_and_room(): void
    {
        $agent = $this->agentWithRoom('all-agent');
        $this->agentWithRoom('all-agent-2');
        $this->actingAs($this->superadminWith([
            Permission::AgentView->value => PermissionScope::All,
            Permission::RoomView->value => PermissionScope::All,
            Permission::RoomManage->value => PermissionScope::All,
        ]), 'superadmin');

        $this->getJson(route('superadmin.admins.index'))->assertOk()->assertJsonCount(2, 'data.data');
        $this->getJson(route('superadmin.admins.show', $agent))->assertOk();
        $this->getJson(route('superadmin.rooms.index'))->assertOk()->assertJsonCount(2, 'data.data');
        $this->patchJson(route('superadmin.rooms.status', $agent->rooms()->first()), ['status' => 'inactive'])->assertOk();
    }

    public function test_platform_permissions_ignore_a_managed_scope(): void
    {
        $superadmin = $this->superadminWith([Permission::SettingsView->value => PermissionScope::Managed]);

        $this->assertSame(PermissionScope::All, $superadmin->scopeFor(Permission::SettingsView));
        $this->assertNull($superadmin->scopeFor(Permission::SettingsManage));
    }

    public function test_room_assignment_outside_scope_is_rejected(): void
    {
        $room = Room::create(['name' => 'Out', 'slug' => 'out-of-scope', 'status' => 'active']);
        $agent = $this->agentWithRoom('assign-agent');
        $this->actingAs($this->superadminWith([
            Permission::AgentManage->value => PermissionScope::All,
            Permission::RoomManage->value => PermissionScope::Managed,
        ]), 'superadmin');

        $this->putJson(route('superadmin.admins.rooms', $agent), ['room_ids' => [$room->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('room_ids');
        $this->assertFalse($agent->rooms()->whereKey($room->id)->exists());
    }
}
