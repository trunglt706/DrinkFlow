<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T09 + T10: packages are data managed by Superadmins; used packages are archived, never deleted.
 */
class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    /**
     * Superadmin holding only the given permissions.
     *
     * @param array<int, string> $keys Permission keys.
     * @return Superadmin Superadmin.
     */
    private function superadminWith(array $keys): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Limited', 'email' => 'limited@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($keys as $key) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => PermissionScope::All->value]);
        }

        return $superadmin;
    }

    /**
     * Valid package form data.
     *
     * @param array<string, mixed> $overrides Field overrides.
     * @return array<string, mixed> Form data.
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Small teams',
            'monthly_price' => 199000,
            'room_limit' => 3,
            'status' => 'active',
            'sort_order' => 1,
        ];
    }

    private function package(array $overrides = []): Package
    {
        return Package::create($this->payload($overrides));
    }

    public function test_packages_table_has_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('packages', ['code', 'name', 'description', 'monthly_price', 'room_limit', 'status', 'sort_order']));
    }

    public function test_superadmin_creates_a_package_and_it_is_audited(): void
    {
        $this->actingAs($this->owner, 'superadmin')
            ->post(route('superadmin.packages.store'), $this->payload(['code' => ' Pro-Plan ']))
            ->assertRedirect(route('superadmin.packages.index'));

        $package = Package::query()->where('code', 'pro-plan')->firstOrFail();
        $this->assertSame(199000, $package->monthly_price);
        $this->assertSame(3, $package->room_limit);
        $this->assertTrue(AuditLog::query()->where('event', 'package.created')->where('target_id', $package->id)->exists());
    }

    public function test_package_validation_rejects_duplicate_or_invalid_values(): void
    {
        $this->package();

        $this->actingAs($this->owner, 'superadmin')
            ->post(route('superadmin.packages.store'), $this->payload(['room_limit' => 0, 'monthly_price' => -1, 'status' => 'bogus']))
            ->assertSessionHasErrors(['code', 'room_limit', 'monthly_price', 'status']);
        $this->actingAs($this->owner, 'superadmin')
            ->post(route('superadmin.packages.store'), $this->payload(['code' => 'bad code!']))
            ->assertSessionHasErrors('code');
        $this->assertSame(1, Package::query()->count());
    }

    public function test_grouped_price_and_room_limit_are_saved_as_integers(): void
    {
        $this->actingAs($this->owner, 'superadmin')
            ->post(route('superadmin.packages.store'), $this->payload(['code' => 'grouped', 'monthly_price' => '1.500.000', 'room_limit' => '1.200']))
            ->assertRedirect(route('superadmin.packages.index'));

        $package = Package::query()->where('code', 'grouped')->firstOrFail();
        $this->assertSame(1500000, $package->monthly_price);
        $this->assertSame(1200, $package->room_limit);

        // Only the separators are dropped: a sign or letters are still rejected.
        $this->actingAs($this->owner, 'superadmin')
            ->post(route('superadmin.packages.store'), $this->payload(['code' => 'signed', 'monthly_price' => '-5.000', 'room_limit' => '3 rooms']))
            ->assertSessionHasErrors(['monthly_price', 'room_limit']);
    }

    public function test_package_form_groups_the_price_and_room_limit(): void
    {
        $package = $this->package(['monthly_price' => 2500000, 'room_limit' => 1500]);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.packages.show', $package))
            ->assertOk()
            ->assertSee('data-number-input', false)
            ->assertSee('value="2.500.000"', false)
            ->assertSee('value="1.500"', false)
            ->assertSee(__('global.common.money_suffix'));
    }

    public function test_list_and_detail_pages_render_for_viewers(): void
    {
        $package = $this->package();
        $viewer = $this->superadminWith(['package.view']);

        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.packages.index'))->assertOk()->assertSee('Starter');
        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.packages.show', $package))->assertOk()->assertSee('starter');
    }

    public function test_view_permission_cannot_change_packages(): void
    {
        $package = $this->package();
        $viewer = $this->superadminWith(['package.view']);

        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.packages.store'), $this->payload(['code' => 'other']))->assertForbidden();
        $this->actingAs($viewer, 'superadmin')->put(route('superadmin.packages.update', $package), $this->payload(['name' => 'Hacked']))->assertForbidden();
        $this->actingAs($viewer, 'superadmin')->delete(route('superadmin.packages.destroy', $package))->assertForbidden();
        $this->assertSame('Starter', $package->fresh()->name);
    }

    public function test_packages_are_hidden_without_permission(): void
    {
        $this->package();

        $this->actingAs($this->superadminWith([]), 'superadmin')->get(route('superadmin.packages.index'))->assertForbidden();
    }

    public function test_admin_session_cannot_reach_package_management(): void
    {
        $admin = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($admin, 'admin')->get(route('superadmin.packages.index'))->assertRedirect(route('superadmin.login.page'));
    }

    public function test_unused_package_can_be_deleted(): void
    {
        $package = $this->package();

        $this->actingAs($this->owner, 'superadmin')->delete(route('superadmin.packages.destroy', $package))->assertRedirect(route('superadmin.packages.index'));

        $this->assertNull($package->fresh());
        $this->assertTrue(AuditLog::query()->where('event', 'package.deleted')->exists());
    }

    public function test_package_with_subscriptions_is_archived_not_deleted(): void
    {
        $package = $this->package();
        $admin = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($admin, $package, $this->owner);

        $this->actingAs($this->owner, 'superadmin')->delete(route('superadmin.packages.destroy', $package))->assertSessionHasErrors('package');
        $this->assertNotNull($package->fresh());

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.packages.archive', $package))->assertRedirect(route('superadmin.packages.show', $package));
        $this->assertSame('archived', $package->fresh()->status->value);
        $this->assertSame(1, $admin->subscriptions()->count());
    }

    public function test_package_requested_by_a_registration_cannot_be_deleted(): void
    {
        $package = $this->package();
        Admin::create(['name' => 'Applicant', 'email' => 'applicant@drinkflow.test', 'password' => 'password123', 'status' => 'pending', 'requested_package_id' => $package->id]);

        $this->actingAs($this->owner, 'superadmin')->delete(route('superadmin.packages.destroy', $package))->assertSessionHasErrors('package');
        $this->assertNotNull($package->fresh());
    }

    public function test_only_active_packages_are_selectable(): void
    {
        $this->package(['code' => 'b', 'sort_order' => 2]);
        $this->package(['code' => 'a', 'sort_order' => 1]);
        $this->package(['code' => 'hidden', 'status' => 'inactive']);
        $this->package(['code' => 'retired', 'status' => 'archived']);

        $this->assertSame(['a', 'b'], Package::query()->selectable()->pluck('code')->all());
    }
}
