<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\InvoiceType;
use App\Enums\PermissionScope;
use App\Enums\PlatformPaymentMethod;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T68 + T69 + T70: horizontal privilege, cross-Agent / cross-Room access and permission scope isolation.
 */
class PlatformSecurityIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Package $small;

    private Package $large;

    private Admin $alice;

    private Admin $bob;

    private Room $aliceRoom;

    private Room $bobRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->small = Package::create(['code' => 'small', 'name' => 'Small', 'monthly_price' => 100000, 'room_limit' => 3, 'status' => 'active']);
        $this->large = Package::create(['code' => 'large', 'name' => 'Large', 'monthly_price' => 900000, 'room_limit' => 30, 'status' => 'active']);
        [$this->alice, $this->aliceRoom] = $this->agent('alice');
        [$this->bob, $this->bobRoom] = $this->agent('bob');
        app(PlatformBillingService::class)->generateInvoices();
    }

    /**
     * Agent with a subscription, one room and one campaign.
     *
     * @param string $name Agent name.
     * @return array{0: Admin, 1: Room} Agent and room.
     */
    private function agent(string $name): array
    {
        $admin = Admin::create(['name' => ucfirst($name), 'email' => "{$name}@drinkflow.test", 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($admin, $this->small);
        $room = Room::create(['name' => ucfirst($name).' room', 'slug' => "{$name}-room", 'status' => 'active', 'owner_admin_id' => $admin->id]);
        $room->admins()->attach($admin->id);
        Campaign::create(['room_id' => $room->id, 'name' => ucfirst($name).' campaign', 'restaurant' => 'Store', 'status' => CampaignStatus::Active]);

        return [$admin, $room];
    }

    /**
     * Superadmin holding one permission with a scope, managing Alice only.
     *
     * @param string|null $key Permission key, or null for none.
     * @param PermissionScope $scope Scope.
     * @return Superadmin Superadmin.
     */
    private function superadmin(?string $key, PermissionScope $scope): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        if ($key !== null) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }
        $superadmin->managedAdmins()->attach($this->alice->id, ['is_primary' => true, 'assigned_at' => now()]);

        return $superadmin;
    }

    // ── T68: horizontal privilege ─────────────────────────────────────────────────────────

    public function test_agent_self_service_only_touches_its_own_account(): void
    {
        $this->actingAs($this->alice, 'admin')->post(route('admin.subscription.change'), ['package_id' => $this->large->id])->assertRedirect();

        // The upgrade is invoiced to Alice only, and applies to her account once paid.
        $upgrade = $this->alice->invoices()->where('type', InvoiceType::Upgrade->value)->firstOrFail();
        $this->assertSame(0, $this->bob->invoices()->where('type', InvoiceType::Upgrade->value)->count());
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(PlatformBillingService::class)->recordPayment($upgrade, $owner, $upgrade->total, PlatformPaymentMethod::BankTransfer);

        $this->assertSame(30, $this->alice->activeSubscription()->firstOrFail()->room_limit_snapshot);
        $this->assertSame(3, $this->bob->activeSubscription()->firstOrFail()->room_limit_snapshot);
        $this->flushSession();
        $this->actingAs($this->bob, 'admin')->get(route('admin.billing.index'))->assertOk()->assertDontSee($upgrade->number);
        $this->flushSession();
        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.index'))->assertOk()->assertDontSee($this->bob->invoices()->value('number'));
        $this->actingAs($this->alice, 'admin')->get(route('admin.subscription.show'))->assertOk()->assertDontSee('bob@drinkflow.test');
    }

    public function test_admin_session_never_reaches_platform_endpoints(): void
    {
        $invoice = $this->bob->invoices()->firstOrFail();

        foreach ([
            route('superadmin.agents.index'), route('superadmin.billing.revenue'), route('superadmin.billing.outstanding'),
            route('superadmin.subscriptions.index'), route('superadmin.packages.index'), route('superadmin.registrations.index'),
        ] as $url) {
            $this->actingAs($this->alice, 'admin')->get($url)->assertRedirect(route('superadmin.login.page'));
        }
        $this->actingAs($this->alice, 'admin')->post(route('superadmin.billing.payments.store', $invoice), ['amount' => $invoice->total, 'method' => 'cash'])->assertRedirect(route('superadmin.login.page'));
        $this->assertSame(0, $invoice->payments()->count());
    }

    // ── T69: cross-Agent / cross-Room ─────────────────────────────────────────────────────

    public function test_agent_cannot_reach_another_agents_room_or_its_records(): void
    {
        $bobCampaign = $this->bobRoom->campaigns()->firstOrFail();

        $this->actingAs($this->alice, 'admin')->getJson(route('admin.dashboard', $this->bobRoom))->assertForbidden();
        $this->actingAs($this->alice, 'admin')->getJson(route('admin.campaigns.show', [$this->bobRoom, $bobCampaign]))->assertForbidden();
        // Through its own room URL, another room's campaign is still out of reach.
        $this->actingAs($this->alice, 'admin')->getJson(route('admin.campaigns.show', [$this->aliceRoom, $bobCampaign]))->assertNotFound();
        $this->actingAs($this->alice, 'admin')->post(route('admin.rooms.archive', $this->bobRoom))->assertForbidden();
        $this->actingAs($this->alice, 'admin')->post(route('admin.rooms.restore', $this->bobRoom))->assertForbidden();
        $this->actingAs($this->alice, 'admin')->getJson(route('admin.socket-token', $this->bobRoom))->assertForbidden();
    }

    public function test_quota_of_one_agent_is_not_shared_with_another(): void
    {
        foreach (['b2', 'b3'] as $slug) {
            Room::create(['name' => $slug, 'slug' => $slug, 'status' => 'active', 'owner_admin_id' => $this->bob->id]);
        }

        $this->actingAs($this->alice, 'admin')->post(route('admin.rooms.store'), ['name' => 'Alice two', 'slug' => 'alice-two', 'timezone' => 'UTC', 'language' => 'vi'])->assertSessionHasNoErrors();
        $this->flushSession();
        $this->actingAs($this->bob, 'admin')->post(route('admin.rooms.store'), ['name' => 'Bob four', 'slug' => 'bob-four', 'timezone' => 'UTC', 'language' => 'vi'])->assertSessionHasErrors('room');
    }

    // ── T70: permission scope isolation ───────────────────────────────────────────────────

    /**
     * Scoped Agent-owned permissions, a listing endpoint and the per-Agent endpoint they guard.
     *
     * @return array<string, array{0: string, 1: string, 2: string|null}> Permission, list route, per-Agent route.
     */
    public static function scopedPermissions(): array
    {
        return [
            'agents' => ['agent.view', 'superadmin.agents.index', 'superadmin.agents.show'],
            'subscriptions' => ['subscription.view', 'superadmin.subscriptions.index', 'superadmin.subscriptions.show'],
            'platform debt' => ['debt.view', 'superadmin.billing.outstanding', 'superadmin.billing.agent'],
            'rooms' => ['room.view', 'superadmin.rooms.page', null],
            'revenue' => ['revenue.view', 'superadmin.billing.revenue', null],
        ];
    }

    #[DataProvider('scopedPermissions')]
    public function test_managed_scope_isolates_other_agents(string $permission, string $listRoute, ?string $agentRoute): void
    {
        $managed = $this->superadmin($permission, PermissionScope::Managed);

        $page = $this->actingAs($managed, 'superadmin')->get(route($listRoute))->assertOk();
        $page->assertDontSee('bob@drinkflow.test')->assertDontSee('Bob room')->assertDontSee((string) $this->bob->invoices()->value('number'));
        if ($agentRoute !== null) {
            $this->actingAs($managed, 'superadmin')->get(route($agentRoute, $this->alice))->assertOk();
            $this->actingAs($managed, 'superadmin')->get(route($agentRoute, $this->bob))->assertForbidden();
        }
    }

    #[DataProvider('scopedPermissions')]
    public function test_all_scope_sees_every_agent(string $permission, string $listRoute, ?string $agentRoute): void
    {
        $all = $this->superadmin($permission, PermissionScope::All);

        $this->actingAs($all, 'superadmin')->get(route($listRoute))->assertOk();
        if ($agentRoute !== null) {
            $this->actingAs($all, 'superadmin')->get(route($agentRoute, $this->bob))->assertOk();
        }
    }

    #[DataProvider('scopedPermissions')]
    public function test_missing_permission_is_forbidden(string $permission, string $listRoute, ?string $agentRoute): void
    {
        $none = $this->superadmin(null, PermissionScope::All);

        $this->actingAs($none, 'superadmin')->get(route($listRoute))->assertForbidden();
        if ($agentRoute !== null) {
            $this->actingAs($none, 'superadmin')->get(route($agentRoute, $this->alice))->assertForbidden();
        }
    }

    public function test_managed_revenue_totals_exclude_other_agents(): void
    {
        $managed = $this->superadmin('revenue.view', PermissionScope::Managed);

        $this->actingAs($managed, 'superadmin')->get(route('superadmin.billing.revenue'))
            ->assertOk()
            ->assertSee('data-kpi="mrr"', false)
            ->assertSee(\App\Support\Helpers\FormatHelper::formatCurrency(100000))
            ->assertDontSee(\App\Support\Helpers\FormatHelper::formatCurrency(200000));
    }

    public function test_scope_grant_on_one_permission_does_not_widen_another(): void
    {
        $superadmin = $this->superadmin('agent.view', PermissionScope::All);
        $superadmin->permissions()->attach(PermissionRecord::query()->where('key', 'debt.view')->value('id'), ['scope' => PermissionScope::Managed->value]);

        $this->actingAs($superadmin, 'superadmin')->get(route('superadmin.agents.show', $this->bob))->assertOk();
        $this->actingAs($superadmin, 'superadmin')->get(route('superadmin.billing.agent', $this->bob))->assertForbidden();
        $this->actingAs($superadmin, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $this->bob, 'tab' => 'billing']))->assertForbidden();
    }
}
