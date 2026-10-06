<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A Superadmin whose Agent-related permissions are scoped to "managed Agents" must never read or change
 * anything of an Agent it does not manage, whatever URL / ID / API it calls, and its dashboard figures
 * only count its own Agents.
 */
class SuperadminScopeProbeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $alice;

    private Admin $bob;

    private Room $aliceRoom;

    private Room $bobRoom;

    private Campaign $bobCampaign;

    private Superadmin $managed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 100000, 'room_limit' => 5, 'status' => 'active']);
        [$this->alice, $this->aliceRoom] = $this->agent('alice', $package);
        [$this->bob, $this->bobRoom, $this->bobCampaign] = $this->agent('bob', $package);
        app(PlatformBillingService::class)->generateInvoices();

        // Every permission; the scoped ones only for the Agents it manages (Alice).
        $this->managed = Superadmin::create(['name' => 'Managed SA', 'email' => 'managed@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach (Permission::cases() as $permission) {
            $this->managed->permissions()->attach(PermissionRecord::query()->where('key', $permission->value)->value('id'), [
                'scope' => ($permission->supportsScope() ? PermissionScope::Managed : PermissionScope::All)->value,
            ]);
        }
        $this->managed->managedAdmins()->attach($this->alice->id, ['is_primary' => true, 'assigned_at' => now()]);
    }

    /**
     * @return array{0: Admin, 1: Room, 2: Campaign}
     */
    private function agent(string $name, Package $package): array
    {
        $admin = Admin::create(['name' => ucfirst($name), 'email' => "{$name}@drinkflow.test", 'phone' => '0900000'.strlen($name).'11', 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($admin, $package);
        $room = Room::create(['name' => ucfirst($name).' room', 'slug' => "{$name}-room", 'status' => 'active', 'owner_admin_id' => $admin->id]);
        $room->admins()->attach($admin->id);
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => ucfirst($name).' campaign', 'restaurant' => 'Store', 'status' => CampaignStatus::Active]);
        $user = GlobalUser::create(['name' => ucfirst($name).' member', 'email' => "{$name}.member@example.test", 'status' => 'active']);
        RoomUser::create(['room_id' => $room->id, 'global_user_id' => $user->id, 'display_name' => ucfirst($name).' member', 'status' => 'active']);

        return [$admin, $room, $campaign];
    }

    private function bobInvoice(): AdminInvoice
    {
        return $this->bob->invoices()->firstOrFail();
    }

    /**
     * Read endpoints addressed with one of Bob's IDs.
     *
     * @return array<string, array{0: string}>
     */
    public static function bobReadEndpoints(): array
    {
        return [
            'agent profile' => ['agents.show'],
            'agent rooms tab' => ['agents.show:rooms'],
            'agent campaigns tab' => ['agents.show:campaigns'],
            'agent billing tab' => ['agents.show:billing'],
            'agent activity tab' => ['agents.show:activity'],
            'admin JSON' => ['admins.show'],
            'admin page' => ['admins.page'],
            'subscription' => ['subscriptions.show'],
            'agent billing' => ['billing.agent'],
            'room JSON' => ['rooms.show'],
            'room page' => ['rooms.page'],
        ];
    }

    private function bobUrl(string $endpoint): string
    {
        return match ($endpoint) {
            'agents.show' => route('superadmin.agents.show', $this->bob),
            'agents.show:rooms', 'agents.show:campaigns', 'agents.show:billing', 'agents.show:activity' => route('superadmin.agents.show', ['admin' => $this->bob, 'tab' => explode(':', $endpoint)[1]]),
            'admins.show' => route('superadmin.admins.show', $this->bob),
            'admins.page' => route('superadmin.admins.detail.page', $this->bob),
            'subscriptions.show' => route('superadmin.subscriptions.show', $this->bob),
            'billing.agent' => route('superadmin.billing.agent', $this->bob),
            'rooms.show' => route('superadmin.rooms.show', $this->bobRoom),
            'rooms.page' => route('superadmin.rooms.detail.page', $this->bobRoom),
        };
    }

    #[DataProvider('bobReadEndpoints')]
    public function test_unmanaged_agent_records_cannot_be_read_by_id(string $endpoint): void
    {
        $response = $this->actingAs($this->managed, 'superadmin')->get($this->bobUrl($endpoint));

        $this->assertContains($response->status(), [403, 404], "{$endpoint} answered {$response->status()}");
        $response->assertDontSee($this->maskedEmail('bob@drinkflow.test'));
    }

    /**
     * Listing pages and APIs: Bob's data never appears.
     *
     * @return array<string, array{0: string}>
     */
    public static function listings(): array
    {
        return [
            'agents' => ['superadmin.agents.index'],
            'admins page' => ['superadmin.admins.page'],
            'admins API' => ['superadmin.admins.index'],
            'subscriptions' => ['superadmin.subscriptions.index'],
            'outstanding' => ['superadmin.billing.outstanding'],
            'revenue' => ['superadmin.billing.revenue'],
            'rooms page' => ['superadmin.rooms.page'],
            'rooms API' => ['superadmin.rooms.index'],
            'campaigns page' => ['superadmin.campaigns.page'],
            'campaigns API' => ['superadmin.campaigns.index'],
            'registrations' => ['superadmin.registrations.index'],
        ];
    }

    #[DataProvider('listings')]
    public function test_listings_only_show_managed_agents(string $route): void
    {
        $this->actingAs($this->managed, 'superadmin')->get(route($route))
            ->assertOk()
            ->assertDontSee($this->maskedEmail('bob@drinkflow.test'))
            ->assertDontSee('Bob room')
            ->assertDontSee('Bob campaign');
    }

    public function test_unmanaged_agent_cannot_be_changed(): void
    {
        $as = $this->actingAs($this->managed, 'superadmin');
        $calls = [
            ['post', route('superadmin.agents.suspend', $this->bob), ['reason' => 'probe probe']],
            ['post', route('superadmin.agents.reactivate', $this->bob), []],
            ['post', route('superadmin.agents.resend-activation', $this->bob), []],
            ['post', route('superadmin.agents.assign', $this->bob), ['superadmin_id' => $this->managed->id]],
            ['patch', route('superadmin.admins.status', $this->bob), ['status' => 'blocked']],
            ['put', route('superadmin.admins.update', $this->bob), ['name' => 'Hacked', 'email' => 'bob@drinkflow.test', 'status' => 'active']],
            ['post', route('superadmin.admins.reset-password', $this->bob), ['password' => 'hacked-pass-1', 'password_confirmation' => 'hacked-pass-1']],
            ['delete', route('superadmin.admins.destroy', $this->bob), []],
            ['post', route('superadmin.subscriptions.change', $this->bob), ['package_id' => Package::query()->value('id')]],
            ['post', route('superadmin.subscriptions.cancel', $this->bob), []],
            ['post', route('superadmin.billing.payments.store', $this->bobInvoice()), ['amount' => 1000, 'method' => 'cash']],
            ['post', route('superadmin.billing.invoices.void', $this->bobInvoice()), ['reason' => 'probe probe']],
            ['patch', route('superadmin.rooms.status', $this->bobRoom), ['status' => 'inactive']],
            ['put', route('superadmin.rooms.update', $this->bobRoom), ['name' => 'Hacked', 'slug' => 'bob-room', 'status' => 'inactive']],
            ['delete', route('superadmin.rooms.destroy', $this->bobRoom), []],
            ['post', route('superadmin.campaigns.force-close', $this->bobCampaign), []],
            ['post', route('superadmin.campaigns.force-cancel', $this->bobCampaign), []],
        ];
        foreach ($calls as [$method, $url, $data]) {
            $response = $as->json(strtoupper($method), $url, $data);
            $this->assertContains($response->status(), [403, 404, 422], strtoupper($method)." {$url} answered {$response->status()}");
        }

        $this->bob->refresh();
        $this->assertSame('Bob', $this->bob->name);
        $this->assertSame('active', $this->bob->status->value);
        $this->assertSame('active', $this->bobRoom->fresh()->status->value);
        $this->assertSame(CampaignStatus::Active, $this->bobCampaign->fresh()->status);
        $this->assertSame(0, $this->bobInvoice()->paid_amount);
    }

    public function test_dashboard_figures_only_count_managed_agents(): void
    {
        foreach (['superadmin.dashboard', 'superadmin.dashboard.analytics', 'superadmin.dashboard.insights'] as $route) {
            $this->actingAs($this->managed, 'superadmin')->getJson(route($route, ['fresh' => 1]))
                ->assertOk()
                ->assertDontSee($this->maskedEmail('bob@drinkflow.test'))
                ->assertDontSee('Bob room')
                ->assertDontSee('Bob campaign');
        }
        $this->assertSame(['Alice room'], array_column(
            $this->actingAs($this->managed, 'superadmin')->getJson(route('superadmin.dashboard.analytics', ['fresh' => 1]))->json('data.rooms'), 'name'
        ));
        $this->actingAs($this->managed, 'superadmin')->getJson(route('superadmin.dashboard'))->assertJsonPath('data.total_rooms', 1);
        // Platform-wide trends (sign-up cohorts, feedback, infrastructure) are not per-Agent figures.
        $this->actingAs($this->managed, 'superadmin')->getJson(route('superadmin.dashboard.trends'))->assertForbidden();
    }
}
