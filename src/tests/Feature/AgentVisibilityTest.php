<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Superadmin\AssignAgentAction;
use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T08: Admin::visibleTo($superadmin) for lists and aggregates over Agents.
 */
class AgentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Admin $mine;

    private Admin $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->mine = Admin::create(['name' => 'Mine', 'email' => 'mine@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->theirs = Admin::create(['name' => 'Theirs', 'email' => 'theirs@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    /**
     * Superadmin holding the given permissions, assigned to the `mine` Agent.
     *
     * @param array<string, PermissionScope> $grants Permission key => scope.
     * @return Superadmin Superadmin.
     */
    private function scoped(array $grants): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped-'.uniqid().'@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }
        app(AssignAgentAction::class)->assign($superadmin, $this->mine, $this->owner);

        return $superadmin;
    }

    /**
     * @param Superadmin $superadmin Viewer.
     * @param Permission $permission Permission exercised.
     * @return array<int, int> Visible Agent IDs, sorted.
     */
    private function visibleIds(Superadmin $superadmin, Permission $permission = Permission::AgentView): array
    {
        return Admin::query()->visibleTo($superadmin, $permission)->orderBy('id')->pluck('id')->all();
    }

    public function test_all_scope_sees_every_agent(): void
    {
        $superadmin = $this->scoped([Permission::AgentView->value => PermissionScope::All]);

        $this->assertSame([$this->mine->id, $this->theirs->id], $this->visibleIds($superadmin));
        $this->assertSame(2, Admin::query()->visibleTo($superadmin)->count());
    }

    public function test_managed_scope_sees_only_assigned_agents_including_in_aggregates(): void
    {
        $superadmin = $this->scoped([Permission::AgentView->value => PermissionScope::Managed]);

        $this->assertSame([$this->mine->id], $this->visibleIds($superadmin));
        $this->assertSame(1, Admin::query()->visibleTo($superadmin)->count());
        // Combined with other constraints and relations (qualified column, no ambiguity).
        $this->assertSame(1, Admin::query()->withCount('rooms')->where('status', 'active')->visibleTo($superadmin)->count());
    }

    public function test_without_permission_visible_to_refuses_access(): void
    {
        $superadmin = $this->scoped([Permission::AuditView->value => PermissionScope::All]);

        $this->expectException(AuthorizationException::class);
        Admin::query()->visibleTo($superadmin)->get();
    }

    public function test_denial_is_rendered_as_403(): void
    {
        $superadmin = $this->scoped([]);
        \Illuminate\Support\Facades\Route::middleware('web')->get('/_test/agents', static fn () => Admin::query()->visibleTo($superadmin)->count());

        $this->getJson('/_test/agents')->assertForbidden();
    }

    public function test_scope_follows_the_permission_exercised(): void
    {
        $superadmin = $this->scoped([
            Permission::AgentView->value => PermissionScope::All,
            Permission::AgentManage->value => PermissionScope::Managed,
        ]);

        $this->assertSame([$this->mine->id, $this->theirs->id], $this->visibleIds($superadmin, Permission::AgentView));
        $this->assertSame([$this->mine->id], $this->visibleIds($superadmin, Permission::AgentManage));
        $this->expectException(AuthorizationException::class);
        $this->visibleIds($superadmin, Permission::AgentApprove);
    }

    public function test_agent_list_endpoints_use_the_scope(): void
    {
        $this->actingAs($this->scoped([Permission::AgentView->value => PermissionScope::Managed]), 'superadmin');

        $this->getJson(route('superadmin.admins.index'))->assertOk()
            ->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $this->mine->id);
        $this->get(route('superadmin.admins.page'))->assertOk()
            ->assertSee($this->maskedEmail('mine@drinkflow.test'))->assertDontSee($this->maskedEmail('theirs@drinkflow.test'));
    }

    public function test_dashboard_agent_total_is_scoped_and_hidden_without_permission(): void
    {
        $this->actingAs($this->owner, 'superadmin');
        $this->getJson(route('superadmin.dashboard'))->assertOk()->assertJsonPath('data.total_admins', 2);

        $this->actingAs($this->scoped([Permission::AgentView->value => PermissionScope::Managed]), 'superadmin');
        $this->getJson(route('superadmin.dashboard'))->assertOk()->assertJsonPath('data.total_admins', 1);

        $this->actingAs($this->scoped([]), 'superadmin');
        $this->getJson(route('superadmin.dashboard'))->assertOk()->assertJsonPath('data.total_admins', null);
    }

    public function test_dashboard_agent_activity_is_scoped_and_empty_without_permission(): void
    {
        $this->actingAs($this->owner, 'superadmin');
        $all = $this->getJson(route('superadmin.dashboard.insights', ['fresh' => 1]))->assertOk()->json('data.admins');
        $this->assertEqualsCanonicalizing([$this->mine->id, $this->theirs->id], array_column($all, 'id'));

        // Same cached payload, narrowed per viewer.
        $this->actingAs($this->scoped([Permission::AgentView->value => PermissionScope::Managed]), 'superadmin');
        $scoped = $this->getJson(route('superadmin.dashboard.insights'))->assertOk()->json('data.admins');
        $this->assertSame([$this->mine->id], array_column($scoped, 'id'));

        $this->actingAs($this->scoped([]), 'superadmin');
        $this->getJson(route('superadmin.dashboard.insights'))->assertOk()->assertJsonCount(0, 'data.admins');
    }
}
