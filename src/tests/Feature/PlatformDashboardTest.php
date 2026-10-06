<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\SecurityEvent;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Dashboard\PlatformDashboardService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T55–T61: permission-aware dashboard widgets whose figures follow the all/managed scope.
 */
class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Admin $alice;

    private Admin $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 400000, 'room_limit' => 4, 'status' => 'active']);
        $this->alice = $this->agent('alice', $package, 1);
        $this->bob = $this->agent('bob', $package, 2);
        app(PlatformBillingService::class)->generateInvoices();
        Admin::create(['name' => 'Waiting', 'email' => 'waiting@drinkflow.test', 'password' => 'password123', 'status' => 'pending', 'registered_at' => now(), 'requested_package_id' => $package->id]);
        SecurityEvent::create(['type' => 'failed_login', 'severity' => 'high', 'ip_address' => '10.0.0.1', 'metadata' => []]);
    }

    private function agent(string $name, Package $package, int $rooms): Admin
    {
        $admin = Admin::create(['name' => ucfirst($name), 'email' => "{$name}@drinkflow.test", 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($admin, $package);
        for ($i = 1; $i <= $rooms; $i++) {
            $room = Room::create(['name' => "{$name} {$i}", 'slug' => "{$name}-{$i}", 'status' => 'active', 'owner_admin_id' => $admin->id]);
            $room->admins()->attach($admin->id);
            Campaign::create(['room_id' => $room->id, 'name' => "{$name} campaign {$i}", 'restaurant' => 'Store', 'status' => CampaignStatus::Active]);
        }

        return $admin;
    }

    /**
     * Superadmin with the given grants (key => scope) managing Alice.
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

    public function test_full_superadmin_sees_every_widget_with_platform_totals(): void
    {
        $widgets = app(PlatformDashboardService::class)->widgetsFor($this->owner);

        $this->assertSame(2, $widgets['agents']['total']);
        $this->assertSame(2, $widgets['agents']['with_subscription']);
        $this->assertSame(800000, $widgets['revenue']['mrr']);
        $this->assertSame(3, $widgets['rooms']['active']);
        $this->assertSame(8, $widgets['rooms']['quota_limit']);
        $this->assertSame(3, $widgets['campaigns']['running']);
        $this->assertSame(1, $widgets['registrations']['count']);
        $this->assertSame(1, $widgets['system']['security_high']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('data-widget="agents"', false)
            ->assertSee('data-widget="registrations"', false)
            ->assertSee('id="sa-analytics"', false);
    }

    public function test_managed_superadmin_widgets_only_cover_assigned_agents(): void
    {
        $managed = PermissionScope::Managed;
        $scoped = $this->scoped(['agent.view' => $managed, 'revenue.view' => $managed, 'room.view' => $managed]);

        $widgets = app(PlatformDashboardService::class)->widgetsFor($scoped);

        $this->assertSame(1, $widgets['agents']['total']);
        $this->assertSame(400000, $widgets['revenue']['mrr']);
        $this->assertSame(400000, $widgets['revenue']['outstanding']);
        $this->assertSame(1, $widgets['rooms']['active']);
        $this->assertSame(4, $widgets['rooms']['quota_limit']);
        $this->assertSame(1, $widgets['campaigns']['running']);
        $this->assertNull($widgets['registrations']);
        $this->assertNull($widgets['system']);
    }

    public function test_widgets_without_permission_are_not_rendered(): void
    {
        $scoped = $this->scoped(['agent.view' => PermissionScope::Managed]);

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('data-widget="agents"', false)
            ->assertDontSee('data-widget="revenue"', false)
            ->assertDontSee('data-widget="rooms"', false)
            ->assertDontSee('data-widget="registrations"', false)
            ->assertDontSee('id="sa-analytics"', false)
            ->assertSee('data-platform-analytics-hidden', false);
    }

    public function test_pending_registration_widget_needs_the_approve_permission(): void
    {
        $approver = $this->scoped(['agent.approve' => PermissionScope::Managed]);

        $this->actingAs($approver, 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('data-widget="registrations"', false)
            ->assertSee('Waiting');
    }

    public function test_dashboard_json_figures_are_scoped(): void
    {
        $scoped = $this->scoped(['agent.view' => PermissionScope::Managed, 'room.view' => PermissionScope::Managed]);

        $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.total_rooms', 1)
            ->assertJsonPath('data.active_campaigns', 1)
            ->assertJsonPath('data.total_admins', 1)
            ->assertJsonPath('data.total_global_users', null)
            ->assertJsonPath('data.system_health', null);
        $this->actingAs($this->owner, 'superadmin')->getJson(route('superadmin.dashboard'))->assertOk()->assertJsonPath('data.total_rooms', 3);
    }

    public function test_restricted_superadmin_gets_analytics_of_its_own_rooms_only(): void
    {
        $scoped = $this->scoped(['room.view' => PermissionScope::Managed, 'agent.view' => PermissionScope::Managed]);

        // Operations figures are counted over the managed Agents' rooms only; the security KPI is hidden.
        $scopedRooms = $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.dashboard.analytics', ['fresh' => 1]))
            ->assertOk()
            ->assertJsonPath('data.kpis.high_security_events.value', null)
            ->json('data.rooms');
        $allRooms = $this->actingAs($this->owner, 'superadmin')->getJson(route('superadmin.dashboard.analytics', ['fresh' => 1]))->assertOk()->json('data.rooms');
        $this->assertCount(1, $scopedRooms);
        $this->assertGreaterThan(1, count($allRooms));
        // Platform-wide trends stay reserved to unrestricted Superadmins.
        $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.dashboard.trends'))->assertForbidden();
        // Without room access there is nothing to count.
        $noRooms = Superadmin::create(['name' => 'No rooms', 'email' => 'norooms@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $noRooms->permissions()->attach(PermissionRecord::query()->where('key', 'agent.view')->value('id'), ['scope' => PermissionScope::Managed->value]);
        $this->actingAs($noRooms, 'superadmin')->getJson(route('superadmin.dashboard.analytics'))->assertForbidden();

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('id="sa-analytics"', false)
            ->assertSee('data-platform="0"', false);
    }

    public function test_insights_hide_security_and_heatmap_without_permission(): void
    {
        $scoped = $this->scoped(['agent.view' => PermissionScope::Managed]);

        $insights = $this->actingAs($scoped, 'superadmin')->getJson(route('superadmin.dashboard.insights', ['fresh' => 1]))->assertOk()->json('data');

        $this->assertSame([], $insights['security']['top_ips']);
        $this->assertSame(0, array_sum(array_column($insights['security']['daily'], 'high')));
        $this->assertSame(0, $insights['heatmap']['total']);
        $full = $this->actingAs($this->owner, 'superadmin')->getJson(route('superadmin.dashboard.insights'))->assertOk()->json('data');
        $this->assertNotSame([], $full['security']['top_ips']);
    }
}
