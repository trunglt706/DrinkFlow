<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminStatus;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\Superadmin;
use App\Models\SuperadminAdmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T45–T54: Agent list and detail tabs, assignment, suspension, and all/managed scope on every Agent view.
 */
class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Admin $alice;

    private Admin $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $package = Package::create(['code' => 'std', 'name' => 'Standard plan', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        $this->alice = $this->agent('alice', $package);
        $this->bob = $this->agent('bob', $package);
        app(PlatformBillingService::class)->generateInvoices();
    }

    private function agent(string $name, Package $package): Admin
    {
        $admin = Admin::create(['name' => ucfirst($name), 'email' => "{$name}@drinkflow.test", 'password' => 'password123', 'status' => 'active', 'company' => ucfirst($name).' Co']);
        app(SubscriptionService::class)->activate($admin, $package);
        $room = Room::create(['name' => ucfirst($name).' room', 'slug' => "{$name}-room", 'status' => 'active', 'owner_admin_id' => $admin->id]);
        $room->admins()->attach($admin->id);
        Campaign::create(['room_id' => $room->id, 'name' => ucfirst($name).' Friday', 'restaurant' => 'Store', 'status' => \App\Enums\CampaignStatus::Closed]);

        return $admin;
    }

    /**
     * Superadmin with permissions (key => scope) managing Alice.
     *
     * @param array<string, PermissionScope> $grants Grants.
     * @return Superadmin Superadmin.
     */
    private function scoped(array $grants): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }
        $superadmin->managedAdmins()->attach($this->alice->id, ['is_primary' => true, 'assigned_at' => now()]);

        return $superadmin;
    }

    public function test_agent_list_shows_package_quota_balance_and_manager(): void
    {
        SuperadminAdmin::query()->create(['superadmin_id' => $this->owner->id, 'admin_id' => $this->alice->id, 'is_primary' => true, 'assigned_at' => now()]);
        Admin::create(['name' => 'Waiting', 'email' => 'waiting@drinkflow.test', 'password' => 'password123', 'status' => AdminStatus::Pending->value]);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.agents.index'))
            ->assertOk()
            ->assertSee($this->maskedEmail('alice@drinkflow.test'))
            ->assertSee('Standard plan')
            ->assertSee('1 / 5')
            ->assertSee('500.000')
            ->assertSee('Owner')
            ->assertDontSee($this->maskedEmail('waiting@drinkflow.test'));
    }

    public function test_every_tab_renders_for_a_full_superadmin(): void
    {
        foreach (['overview' => 'Alice Co', 'subscription' => 'Standard plan', 'rooms' => '/alice-room', 'campaigns' => 'Alice Friday', 'billing' => 'INV-', 'activity' => 'subscription'] as $tab => $text) {
            $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->alice, 'tab' => $tab]))->assertOk()->assertSee($text);
        }
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->alice, 'tab' => 'bogus']))->assertNotFound();
    }

    public function test_managed_superadmin_only_sees_assigned_agents(): void
    {
        $scoped = $this->scoped(['agent.view' => PermissionScope::Managed]);

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.index'))->assertOk()->assertSee($this->maskedEmail('alice@drinkflow.test'))->assertDontSee($this->maskedEmail('bob@drinkflow.test'));
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', $this->alice))->assertOk();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', $this->bob))->assertForbidden();
    }

    public function test_columns_and_tabs_follow_their_own_permission_scope(): void
    {
        // Sees every Agent, but money and subscriptions only for Alice; no room or audit permission.
        $scoped = $this->scoped([
            'agent.view' => PermissionScope::All,
            'debt.view' => PermissionScope::Managed,
            'subscription.view' => PermissionScope::Managed,
        ]);

        $list = $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.index'))->assertOk();
        $list->assertSee($this->maskedEmail('bob@drinkflow.test'))->assertSee('•••');

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->alice, 'tab' => 'billing']))->assertOk();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->bob, 'tab' => 'billing']))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->bob, 'tab' => 'subscription']))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->alice, 'tab' => 'rooms']))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->alice, 'tab' => 'activity']))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->bob]))->assertOk()->assertDontSee('500.000');
    }

    public function test_assign_and_unassign_managers(): void
    {
        $manager = Superadmin::create(['name' => 'Manager', 'email' => 'manager@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.agents.assign', $this->bob), ['superadmin_id' => $manager->id, 'is_primary' => 1])->assertRedirect();
        $this->assertTrue(SuperadminAdmin::query()->where('admin_id', $this->bob->id)->where('superadmin_id', $manager->id)->where('is_primary', true)->exists());

        $this->actingAs($this->owner, 'superadmin')->delete(route('superadmin.agents.unassign', ['admin' => $this->bob, 'superadmin' => $manager]))->assertRedirect();
        $this->assertFalse(SuperadminAdmin::query()->where('admin_id', $this->bob->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['event' => 'agent.unassigned', 'target_id' => $this->bob->id]);
    }

    public function test_managed_superadmin_cannot_assign_agents_outside_scope(): void
    {
        $scoped = $this->scoped(['agent.view' => PermissionScope::All, 'agent.manage' => PermissionScope::Managed]);

        $this->actingAs($scoped, 'superadmin')->post(route('superadmin.agents.assign', $this->bob), ['superadmin_id' => $scoped->id])->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->post(route('superadmin.agents.suspend', $this->bob), ['reason' => 'Unpaid'])->assertForbidden();
        $this->assertSame(AdminStatus::Active, $this->bob->fresh()->status);
    }

    public function test_suspend_blocks_the_agent_and_reactivate_restores_it(): void
    {
        $room = $this->alice->ownedRooms()->firstOrFail();

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.agents.suspend', $this->alice), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.agents.suspend', $this->alice), ['reason' => 'Unpaid invoices'])->assertRedirect();
        $this->assertSame(AdminStatus::Suspended, $this->alice->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'agent.suspended', 'target_id' => $this->alice->id]);
        $this->assertNotNull($this->alice->activeSubscription()->first());

        // A collaborator of the suspended Agent's room is blocked too.
        $helper = Admin::create(['name' => 'Helper', 'email' => 'helper@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room->admins()->attach($helper->id);
        $this->actingAs($helper, 'admin')->getJson(route('admin.dashboard', $room))->assertForbidden();

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.agents.suspend', $this->alice), ['reason' => 'Again'])->assertSessionHasErrors('status');
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.agents.reactivate', $this->alice))->assertRedirect();
        $this->assertSame(AdminStatus::Active, $this->alice->fresh()->status);
        $this->actingAs($helper, 'admin')->getJson(route('admin.dashboard', $room))->assertOk();
    }

    public function test_view_only_superadmin_cannot_manage_agents(): void
    {
        $viewer = $this->scoped(['agent.view' => PermissionScope::All]);

        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.agents.show', $this->alice))->assertOk()->assertDontSee(route('superadmin.agents.suspend', $this->alice), false);
        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.agents.suspend', $this->alice), ['reason' => 'No'])->assertForbidden();
        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.agents.assign', $this->alice), ['superadmin_id' => $viewer->id])->assertForbidden();
    }

    public function test_managed_scope_applies_to_every_agent_owned_view(): void
    {
        $managed = PermissionScope::Managed;
        $scoped = $this->scoped([
            'agent.view' => $managed, 'room.view' => $managed, 'subscription.view' => $managed,
            'revenue.view' => $managed, 'debt.view' => $managed,
        ]);
        $bobRoom = $this->bob->ownedRooms()->firstOrFail();

        $pages = [
            route('superadmin.agents.index'),
            route('superadmin.admins.page'),
            route('superadmin.rooms.page'),
            route('superadmin.campaigns.page'),
            route('superadmin.subscriptions.index'),
            route('superadmin.billing.outstanding'),
        ];
        foreach ($pages as $page) {
            $this->actingAs($scoped, 'superadmin')->get($page)->assertOk()->assertDontSee($this->maskedEmail('bob@drinkflow.test'))->assertDontSee('Bob room')->assertDontSee('Bob Friday');
        }
        $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.admins.show', $this->bob))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.rooms.show', $bobRoom))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.subscriptions.show', $this->bob))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.billing.agent', $this->bob))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.agents.show', $this->bob))->assertForbidden();
    }
}
