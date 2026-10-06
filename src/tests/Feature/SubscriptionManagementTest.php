<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PermissionScope;
use App\Enums\PlatformPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\AdminSubscription;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T27–T33: subscription page, quota UI, upgrade, downgrade, history, lifecycle and access enforcement.
 */
class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Package $small;

    private Package $large;

    private Admin $agent;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfDay());
        $this->service = app(SubscriptionService::class);
        $this->small = Package::create(['code' => 'small', 'name' => 'Small', 'monthly_price' => 300000, 'room_limit' => 2, 'status' => 'active', 'sort_order' => 1]);
        $this->large = Package::create(['code' => 'large', 'name' => 'Large', 'monthly_price' => 900000, 'room_limit' => 10, 'status' => 'active', 'sort_order' => 2]);
        $this->agent = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->service->activate($this->agent, $this->small);
    }

    private function room(string $slug, string $status = 'active', ?Admin $owner = null): Room
    {
        $owner ??= $this->agent;
        $room = Room::create(['name' => $slug, 'slug' => $slug, 'status' => $status, 'owner_admin_id' => $owner->id]);
        $room->admins()->attach($owner->id);

        return $room;
    }

    private function current(?Admin $admin = null): AdminSubscription
    {
        return ($admin ?? $this->agent)->activeSubscription()->firstOrFail();
    }

    public function test_subscription_page_shows_package_price_usage_status_and_dates(): void
    {
        $this->room('one');

        $this->actingAs($this->agent, 'admin')->get(route('admin.subscription.show'))
            ->assertOk()
            ->assertSee('Small')
            ->assertSee('300.000')
            ->assertSee(__('platform.rooms.quota_usage', ['used' => 1, 'limit' => 2]))
            ->assertSee('data-subscription-status="active"', false)
            ->assertSee(now()->addMonthNoOverflow()->toAppDate())
            ->assertSee(__('platform.subscriptions.upgrade'));
    }

    public function test_agent_without_subscription_sees_the_empty_state(): void
    {
        $legacy = Admin::create(['name' => 'Legacy', 'email' => 'legacy@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($legacy, 'admin')->get(route('admin.subscription.show'))
            ->assertOk()
            ->assertSee('data-subscription-status="none"', false)
            ->assertSee(__('platform.subscriptions.none_title'));
        $this->actingAs($legacy, 'admin')->post(route('admin.subscription.change'), ['package_id' => $this->large->id])->assertSessionHasErrors('package_id');
    }

    public function test_auto_renew_can_be_switched_off_and_on_behind_a_confirmation(): void
    {
        $page = fn () => $this->actingAs($this->agent, 'admin')->get(route('admin.subscription.show'))->assertOk();

        $page()->assertSee('data-auto-renew="on"', false)
            ->assertSee('data-confirm-message="'.e(__('platform.subscriptions.cancel_confirm', ['date' => $this->current()->expires_at->toAppDate()])).'"', false)
            ->assertDontSee('return confirm(', false);

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.cancel'))->assertRedirect();
        $page()->assertSee('data-auto-renew="off"', false)->assertSee(__('platform.subscriptions.resume'));

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.resume'))->assertRedirect();
        $page()->assertSee('data-auto-renew="on"', false);

        // With auto-renew on, the scheduler renews the ended period.
        $this->travelTo($this->current()->expires_at->copy()->addMinute());
        $this->assertSame(1, $this->service->processDuePeriods()['renewed']);
        $this->assertTrue($this->current()->expires_at->isFuture());
    }

    public function test_account_pages_use_the_shared_empty_state(): void
    {
        $this->actingAs($this->agent, 'admin')->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee(__('platform.billing.no_invoices'))
            ->assertSee('material-symbols-outlined text-[26px]">receipt_long', false);
        $this->actingAs($this->agent, 'admin')->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('material-symbols-outlined text-[26px]">meeting_room', false);
    }

    public function test_upgrade_is_invoiced_first_and_applies_with_a_proration_credit_once_paid(): void
    {
        $old = $this->current();
        $this->travel(15)->days();

        // An Agent's upgrade is billed first: the current package stays until the upgrade invoice is paid.
        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.change'), ['package_id' => $this->large->id])
            ->assertRedirect(route('admin.subscription.show'))->assertSessionHas('status');
        $this->assertSame($old->id, $this->current()->id);
        $invoice = AdminInvoice::query()->where('admin_id', $this->agent->id)->where('type', InvoiceType::Upgrade->value)->firstOrFail();
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame($this->large->id, $invoice->package_id);
        $this->assertSame(900000 - $invoice->credit, $invoice->total);

        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(PlatformBillingService::class)->recordPayment($invoice, $owner, $invoice->total, PlatformPaymentMethod::BankTransfer);

        $new = $this->current();
        $this->assertSame($new->id, $invoice->fresh()->admin_subscription_id);
        $this->assertSame($invoice->credit, $new->proration_credit);
        $this->assertSame($this->large->id, $new->package_id);
        $this->assertSame(10, $new->room_limit_snapshot);
        $this->assertSame($old->id, $new->previous_subscription_id);
        $this->assertSame(SubscriptionStatus::Superseded, $old->fresh()->status);
        $this->assertGreaterThan(0, $new->proration_credit);
        $this->assertLessThan(300000, $new->proration_credit);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subscription.upgraded', 'target_id' => $this->agent->id]);
    }

    public function test_upgrade_request_can_be_resubmitted_within_the_same_second(): void
    {
        $this->freezeSecond();
        app(PlatformBillingService::class)->generateInvoices();

        // A double submit (or switching the target package right away) replaces the unpaid request.
        $first = app(SubscriptionUpgradeService::class)->request($this->agent, $this->large);
        $second = app(SubscriptionUpgradeService::class)->request($this->agent, $this->large);

        $this->assertSame(InvoiceStatus::Void, $first->fresh()->status);
        $this->assertSame(InvoiceStatus::Open, $second->status);
        $this->assertSame(1, AdminInvoice::query()->where('admin_id', $this->agent->id)->where('type', InvoiceType::Upgrade->value)->outstanding()->count());
    }

    public function test_downgrade_is_scheduled_for_the_period_end(): void
    {
        $this->service->changePackage($this->agent, $this->large, $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']));
        $current = $this->current();

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.change'), ['package_id' => $this->small->id])->assertRedirect();

        $current->refresh();
        $this->assertSame(SubscriptionStatus::Active, $current->status);
        $this->assertSame($this->small->id, $current->scheduled_package_id);
        $this->assertSame(10, $current->room_limit_snapshot);

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.scheduled.cancel'))->assertRedirect();
        $this->assertNull($current->fresh()->scheduled_package_id);
    }

    public function test_downgrade_is_refused_when_rooms_do_not_fit(): void
    {
        $this->service->changePackage($this->agent, $this->large, $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']));
        $this->room('a');
        $this->room('b');
        $this->room('c', 'inactive');
        $this->room('d', 'archived');

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.change'), ['package_id' => $this->small->id])
            ->assertSessionHasErrors(['package_id' => __('platform.subscriptions.too_many_rooms', ['used' => 3, 'limit' => 2])]);
        $this->assertNull($this->current()->scheduled_package_id);
    }

    public function test_lifecycle_renews_applies_downgrades_and_ends_cancellations(): void
    {
        $first = $this->current();
        $this->travel(1)->months();
        $this->travel(1)->hours();

        $this->artisan('subscriptions:process')->assertSuccessful();
        $first->refresh();
        $this->assertSame(SubscriptionStatus::Active, $first->status);
        $this->assertTrue($first->expires_at->greaterThan(now()));
        $this->assertSame(300000, $first->price_snapshot);

        // Idempotent: nothing is due anymore.
        $this->assertSame(['renewed' => 0, 'changed' => 0, 'cancelled' => 0], $this->service->processDuePeriods());

        $this->actingAs($this->agent, 'admin')->post(route('admin.subscription.cancel'))->assertRedirect();
        $this->assertTrue($first->fresh()->cancel_at_period_end);
        $this->travelTo($first->expires_at->copy()->addMinute());
        $this->service->processDuePeriods();
        $this->assertSame(SubscriptionStatus::Cancelled, $first->fresh()->status);
        $this->assertNull($this->agent->activeSubscription()->first());
    }

    public function test_scheduled_downgrade_applies_at_period_end_or_is_dropped_when_rooms_grew(): void
    {
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->service->changePackage($this->agent, $this->large, $owner);
        $this->service->changePackage($this->agent, $this->small);
        $large = $this->current();

        $this->travelTo($large->expires_at->copy()->addMinute());
        $this->service->processDuePeriods();
        $this->assertSame(SubscriptionStatus::Superseded, $large->fresh()->status);
        $this->assertSame(2, $this->current()->room_limit_snapshot);

        // Second agent: rooms grew after scheduling, so the downgrade is dropped and the plan renews.
        $other = Admin::create(['name' => 'Other', 'email' => 'other@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->service->activate($other, $this->large);
        $this->service->changePackage($other, $this->small);
        foreach (['x', 'y', 'z'] as $slug) {
            $this->room($slug, 'active', $other);
        }
        $this->travelTo($this->current($other)->expires_at->copy()->addMinute());
        $this->service->processDuePeriods();
        $this->assertSame(10, $this->current($other)->room_limit_snapshot);
        $this->assertNull($this->current($other)->scheduled_package_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subscription.scheduled_change_dropped', 'target_id' => $other->id]);
    }

    public function test_history_lists_every_subscription(): void
    {
        $this->service->changePackage($this->agent, $this->large, $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']));

        $response = $this->actingAs($this->agent, 'admin')->get(route('admin.subscription.show'))->assertOk();
        $response->assertSee(__('platform.subscriptions.status.superseded'))->assertSee('Large');
        $this->assertSame(2, $this->agent->subscriptions()->count());
    }

    public function test_room_area_is_blocked_after_the_subscription_ended(): void
    {
        $room = $this->room('team');
        $this->actingAs($this->agent, 'admin')->get(route('admin.dashboard.page', $room))->assertOk();

        $this->service->cancelNow($this->agent);

        $this->actingAs($this->agent, 'admin')->get(route('admin.dashboard.page', $room))
            ->assertRedirect(route('admin.subscription.show'))
            ->assertSessionHasErrors(['subscription' => __('platform.subscriptions.blocked.no_subscription')]);
        $this->actingAs($this->agent, 'admin')->getJson(route('admin.dashboard', $room))->assertForbidden();
        // Collaborators of that room are blocked too.
        $helper = Admin::create(['name' => 'Helper', 'email' => 'helper@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room->admins()->attach($helper->id);
        $this->flushSession();
        $this->actingAs($helper, 'admin')->get(route('admin.dashboard.page', $room))->assertRedirect(route('admin.rooms.index'));
    }

    public function test_legacy_room_without_subscription_history_is_not_blocked(): void
    {
        $legacy = Admin::create(['name' => 'Legacy', 'email' => 'legacy@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room = $this->room('legacy', 'active', $legacy);

        $this->actingAs($legacy, 'admin')->get(route('admin.dashboard.page', $room))->assertOk();
    }

    public function test_superadmin_lists_and_manages_subscriptions_within_scope(): void
    {
        $other = Admin::create(['name' => 'Other agent', 'email' => 'other@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->service->activate($other, $this->small);
        $scoped = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach (['subscription.view', 'subscription.manage'] as $key) {
            $scoped->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => PermissionScope::Managed->value]);
        }
        $scoped->managedAdmins()->attach($this->agent->id, ['is_primary' => true, 'assigned_at' => now()]);

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.subscriptions.index'))
            ->assertOk()->assertSee($this->maskedEmail('agent@drinkflow.test'))->assertDontSee($this->maskedEmail('other@drinkflow.test'));
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.subscriptions.show', $other))->assertForbidden();
        $this->actingAs($scoped, 'superadmin')->post(route('superadmin.subscriptions.change', $other), ['package_id' => $this->large->id])->assertForbidden();

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.subscriptions.show', $this->agent))->assertOk();
        $this->actingAs($scoped, 'superadmin')->post(route('superadmin.subscriptions.change', $this->agent), ['package_id' => $this->large->id])->assertRedirect();
        $this->assertSame(10, $this->current()->room_limit_snapshot);
        $this->assertSame($scoped->id, $this->current()->approved_by_superadmin_id);
    }

    public function test_superadmin_starts_a_subscription_for_a_legacy_agent_and_can_cancel_it(): void
    {
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $legacy = Admin::create(['name' => 'Legacy', 'email' => 'legacy@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($owner, 'superadmin')->post(route('superadmin.subscriptions.change', $legacy), ['package_id' => $this->small->id])->assertRedirect();
        $this->assertSame(2, $this->current($legacy)->room_limit_snapshot);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subscription.started', 'target_id' => $legacy->id]);

        $this->actingAs($owner, 'superadmin')->post(route('superadmin.subscriptions.cancel', $legacy))->assertRedirect();
        $this->assertNull($legacy->activeSubscription()->first());
    }

    public function test_view_only_superadmin_cannot_change_subscriptions(): void
    {
        $viewer = Superadmin::create(['name' => 'Viewer', 'email' => 'viewer@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $viewer->permissions()->attach(PermissionRecord::query()->where('key', 'subscription.view')->value('id'), ['scope' => PermissionScope::All->value]);

        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.subscriptions.show', $this->agent))->assertOk()->assertDontSee(route('superadmin.subscriptions.change', $this->agent), false);
        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.subscriptions.change', $this->agent), ['package_id' => $this->large->id])->assertForbidden();
        $this->assertSame(2, $this->current()->room_limit_snapshot);
    }
}
