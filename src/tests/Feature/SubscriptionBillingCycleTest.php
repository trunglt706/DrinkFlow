<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PermissionScope;
use App\Enums\SubscriptionStatus;
use App\Events\SuperadminNotificationCreated;
use App\Mail\AgentBillingSuspendedMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\AdminSubscription;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Models\SuperadminNotification;
use App\Services\Billing\BillingEnforcementService;
use App\Services\Billing\BillingSuspensionNotifier;
use App\Services\Billing\PlatformBillingService;
use App\Services\Notification\SystemNotificationChannelDispatcher;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Billing cycles of N months, daily renewal reminders, renewal invoices and automatic suspension
 * with its email + Superadmin alerts, and the Superadmin view of pay-to-activate registrations.
 */
class SubscriptionBillingCycleTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private SubscriptionService $subscriptions;

    private PlatformBillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->startOfMonth()->addDays(2)->setTime(9, 0));
        $this->package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 100000, 'room_limit' => 3, 'status' => 'active']);
        $this->subscriptions = app(SubscriptionService::class);
        $this->billing = app(PlatformBillingService::class);
    }

    private function agentOnCycle(string $email, int $months): Admin
    {
        $admin = Admin::create(['name' => $email, 'email' => $email, 'password' => 'password123', 'status' => 'active']);
        $this->subscriptions->activate($admin, $this->package, null, null, 0, $months);

        return $admin;
    }

    /**
     * @param array<string, PermissionScope> $grants Permission key => scope.
     */
    private function superadminWith(string $email, array $grants): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'SA '.$email, 'email' => $email, 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }

        return $superadmin;
    }

    public function test_a_six_month_cycle_bills_and_renews_six_months_at_a_time(): void
    {
        $admin = $this->agentOnCycle('six@drinkflow.test', 6);
        $subscription = $admin->subscriptions()->sole();
        $this->assertTrue($subscription->expires_at->equalTo($subscription->starts_at->copy()->addMonthsNoOverflow(6)));

        $this->billing->generateInvoices();
        $first = $admin->invoices()->sole();
        $this->assertSame(600000, $first->total);

        // At the end of the period the subscription renews for 6 more months with a new unpaid invoice.
        $this->travelTo($subscription->expires_at->copy()->addMinute());
        $this->subscriptions->processDuePeriods();
        $this->billing->generateInvoices();

        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->equalTo($subscription->starts_at->copy()->addMonthsNoOverflow(12)));
        $renewal = $admin->invoices()->latest('id')->first();
        $this->assertNotSame($first->id, $renewal->id);
        $this->assertSame(InvoiceStatus::Open, $renewal->status);
        $this->assertSame(600000, $renewal->total);
        $this->assertTrue($renewal->period_end->equalTo($subscription->expires_at));
    }

    public function test_renewal_reminder_is_sent_once_per_period_within_the_window(): void
    {
        config(['platform.billing.renewal_reminder_days' => 7]);
        $soon = $this->agentOnCycle('soon@drinkflow.test', 1);
        $later = $this->agentOnCycle('later@drinkflow.test', 6);
        $stopping = $this->agentOnCycle('stopping@drinkflow.test', 1);
        $stopping->subscriptions()->update(['cancel_at_period_end' => true]);
        $this->travelTo($soon->subscriptions()->sole()->expires_at->copy()->subDays(3));

        $this->artisan('subscriptions:remind-renewals')->assertSuccessful();
        $this->artisan('subscriptions:remind-renewals')->assertSuccessful();

        Mail::assertQueued(SubscriptionRenewalReminderMail::class, 1);
        Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn (SubscriptionRenewalReminderMail $mail): bool => $mail->hasTo('soon@drinkflow.test'));
        $this->assertNotNull($soon->subscriptions()->sole()->renewal_reminder_sent_for);
        $this->assertNull($later->subscriptions()->sole()->renewal_reminder_sent_for);

        // The next period gets its own reminder.
        $subscription = $soon->subscriptions()->sole();
        $this->travelTo($subscription->expires_at->copy()->addMinute());
        $this->subscriptions->processDuePeriods();
        $this->travelTo($subscription->fresh()->expires_at->copy()->subDays(2));
        $this->artisan('subscriptions:remind-renewals')->assertSuccessful();
        Mail::assertQueued(SubscriptionRenewalReminderMail::class, 2);
    }

    public function test_overdue_past_grace_suspends_and_alerts_the_agent_and_responsible_superadmins(): void
    {
        config(['platform.billing.auto_suspend' => true, 'platform.billing.due_days' => 7, 'platform.billing.grace_days' => 7]);
        Event::fake([SuperadminNotificationCreated::class]);
        $this->mock(SystemNotificationChannelDispatcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('dispatch')->once()->withArgs(static fn (array $payload): bool => $payload['event'] === BillingSuspensionNotifier::TYPE)->andReturn(1);
        });
        $admin = $this->agentOnCycle('late@drinkflow.test', 1);
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $manager = $this->superadminWith('manager@drinkflow.test', ['debt.view' => PermissionScope::Managed]);
        $manager->managedAdmins()->attach($admin->id, ['is_primary' => true, 'assigned_at' => now()]);
        $outsider = $this->superadminWith('outsider@drinkflow.test', ['debt.view' => PermissionScope::Managed]);
        $this->billing->generateInvoices();

        $this->travel(15)->days();
        $this->billing->processOverdue();
        $this->assertSame(1, app(BillingEnforcementService::class)->suspendOverdueAgents());

        $this->assertSame(AdminStatus::Suspended, $admin->fresh()->status);
        Mail::assertQueued(AgentBillingSuspendedMail::class, fn (AgentBillingSuspendedMail $mail): bool => $mail->hasTo('late@drinkflow.test') && $mail->invoice !== null);
        $recipients = SuperadminNotification::query()->where('type', BillingSuspensionNotifier::TYPE)->pluck('superadmin_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
        $this->assertSame([$owner->id, $manager->id], $recipients);
        $this->assertNotContains($outsider->id, $recipients);
        Event::assertDispatchedTimes(SuperadminNotificationCreated::class, 2);
    }

    public function test_an_unpaid_registration_invoice_never_suspends(): void
    {
        config(['platform.billing.auto_suspend' => true]);
        $admin = Admin::create(['name' => 'New', 'email' => 'new@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $pending = $this->subscriptions->createPending($admin, $this->package, 6);
        $this->billing->invoicePendingSubscription($pending);

        $this->travel(30)->days();
        $this->billing->processOverdue();

        $this->assertSame(0, app(BillingEnforcementService::class)->suspendOverdueAgents());
        $this->assertSame(AdminStatus::Active, $admin->fresh()->status);
        Mail::assertNotQueued(AgentBillingSuspendedMail::class);
    }

    public function test_superadmin_sees_the_first_invoice_instead_of_approval_and_rejection_voids_it(): void
    {
        config(['captcha.disable' => true]);
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->post(route('admin.register'), [
            'name' => 'Applicant', 'email' => 'applicant@example.test', 'phone' => '0901234567',
            'package_id' => $this->package->id, 'period_quantity' => 1, 'period_unit' => 'year',
        ])->assertRedirect(route('admin.register.pending'));
        $admin = Admin::query()->where('email', 'applicant@example.test')->firstOrFail();
        $invoice = AdminInvoice::query()->where('admin_id', $admin->id)->sole();

        $this->actingAs($owner, 'superadmin')->get(route('superadmin.registrations.show', $admin))
            ->assertOk()
            ->assertSee('data-registration-awaiting-payment', false)
            ->assertSee($invoice->number)
            ->assertDontSee(route('superadmin.registrations.approve', $admin), false);
        $this->actingAs($owner, 'superadmin')->post(route('superadmin.registrations.approve', $admin))
            ->assertSessionHasErrors(['registration' => __('platform.registrations.activates_on_payment')]);
        $this->assertSame(SubscriptionStatus::Pending, $admin->subscriptions()->sole()->status);

        $this->actingAs($owner, 'superadmin')->post(route('superadmin.registrations.reject', $admin), ['reason' => 'Duplicate'])->assertRedirect();
        $this->assertSame(AdminStatus::Rejected, $admin->fresh()->status);
        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::Cancelled, AdminSubscription::query()->where('admin_id', $admin->id)->sole()->status);
    }
}
