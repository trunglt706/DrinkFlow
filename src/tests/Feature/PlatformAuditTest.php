<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Superadmin\AssignAgentAction;
use App\Actions\Superadmin\ManageSuperadminAction;
use App\Enums\PlatformPaymentMethod;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * T62–T67: every platform decision leaves an append-only audit trail with its actor.
 */
class PlatformAuditTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['captcha.disable' => true]);
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 300000, 'room_limit' => 3, 'status' => 'active']);
    }

    /**
     * Audit events of a target, oldest first.
     *
     * @param string $type Target type.
     * @param int $id Target ID.
     * @return array<int, string> Event keys.
     */
    private function events(string $type, int $id): array
    {
        return AuditLog::query()->where('target_type', $type)->where('target_id', $id)->orderBy('id')->pluck('event')->all();
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = AuditLog::create(['actor_type' => 'system', 'event' => 'test.event', 'target_type' => 'system', 'target_id' => 1, 'created_at' => now()]);

        $this->assertThrows(fn () => $log->update(['event' => 'tampered']), \LogicException::class);
        $this->assertThrows(fn () => $log->delete(), \LogicException::class);
        $this->assertSame('test.event', $log->fresh()->event);
    }

    public function test_registration_flow_is_audited_with_actors(): void
    {
        $this->post(route('admin.register'), [
            'name' => 'Applicant', 'email' => 'applicant@example.test', 'phone' => '0901234567',
            'package_id' => $this->package->id, 'period_quantity' => 6, 'period_unit' => 'month',
        ])->assertRedirect();
        $admin = Admin::query()->where('email', 'applicant@example.test')->firstOrFail();
        $this->post(app(\App\Services\Admin\AdminActivationService::class)->link($admin), [
            'sign_in_value' => 'secret-pass-1', 'sign_in_value_confirmation' => 'secret-pass-1',
        ])->assertRedirect();
        // Pay-to-activate: the Superadmin records the payment of the first invoice, which starts the package.
        $invoice = $admin->invoices()->firstOrFail();
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.payments.store', $invoice), [
            'amount' => $invoice->total, 'method' => 'bank_transfer',
        ])->assertRedirect();

        $this->assertSame(['admin.registered', 'agent.activated', 'subscription.started'], $this->events('admin', $admin->id));
        $started = AuditLog::query()->where('event', 'subscription.started')->firstOrFail();
        $this->assertSame(AuditLog::ACTOR_SUPERADMIN, $started->actor_type);
        $this->assertSame($this->owner->id, $started->actor_id);
        $this->assertSame(300000, $started->after_data['price_snapshot']);
        $this->assertSame(6, $started->after_data['period_months']);
        $this->assertSame(AuditLog::ACTOR_SYSTEM, AuditLog::query()->where('event', 'admin.registered')->value('actor_type'));
    }

    public function test_package_and_subscription_changes_are_audited(): void
    {
        $large = Package::create(['code' => 'large', 'name' => 'Large', 'monthly_price' => 900000, 'room_limit' => 9, 'status' => 'active']);
        $this->actingAs($this->owner, 'superadmin')->put(route('superadmin.packages.update', $this->package), [
            'code' => 'std', 'name' => 'Standard+', 'monthly_price' => 350000, 'room_limit' => 3, 'status' => 'active',
        ]);
        $agent = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $service = app(SubscriptionService::class);
        $service->changePackage($agent, $this->package->fresh(), $this->owner);
        $service->changePackage($agent, $large, $this->owner);
        $service->setCancelAtPeriodEnd($agent, true);

        $this->assertSame(['package.updated'], $this->events('package', $this->package->id));
        $update = AuditLog::query()->where('event', 'package.updated')->firstOrFail();
        $this->assertSame(300000, $update->before_data['monthly_price']);
        $this->assertSame(350000, $update->after_data['monthly_price']);
        $this->assertSame(['subscription.started', 'subscription.upgraded', 'subscription.cancel_requested'], $this->events('admin', $agent->id));
    }

    public function test_invoice_and_payment_events_are_audited(): void
    {
        $agent = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($agent, $this->package);
        $billing = app(PlatformBillingService::class);
        $billing->generateInvoices();
        $invoice = $agent->invoices()->firstOrFail();
        $this->actingAs($this->owner, 'superadmin');
        $billing->recordPayment($invoice, $this->owner, 100000, PlatformPaymentMethod::BankTransfer, 'REF-1');
        $this->travel(10)->days();
        $billing->processOverdue();

        $this->assertSame(['invoice.issued', 'invoice.payment_recorded', 'invoice.overdue'], $this->events('admin_invoice', $invoice->id));
        $payment = AuditLog::query()->where('event', 'invoice.payment_recorded')->firstOrFail();
        $this->assertSame(100000, $payment->after_data['amount']);
        $this->assertSame(AuditLog::ACTOR_SUPERADMIN, $payment->actor_type);
    }

    public function test_permission_and_assignment_changes_are_audited(): void
    {
        $this->actingAs($this->owner, 'superadmin');
        $other = app(ManageSuperadminAction::class)->create(['name' => 'Other', 'email' => 'other@drinkflow.test', 'password' => 'password123']);
        app(ManageSuperadminAction::class)->syncPermissions($other, $this->owner, ['agent.view' => 'managed']);
        $agent = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $assign = app(AssignAgentAction::class);
        $assign->assign($other, $agent, $this->owner, true);
        $assign->unassign($other, $agent);

        $this->assertSame(['superadmin.created', 'superadmin.permissions_updated'], $this->events('superadmin', $other->id));
        $this->assertSame(['managed'], array_values(AuditLog::query()->where('event', 'superadmin.permissions_updated')->firstOrFail()->after_data['permissions']));
        $this->assertSame(['agent.assigned', 'agent.unassigned'], $this->events('admin', $agent->id));
    }

    public function test_superadmin_audit_page_shows_only_superadmin_actions_with_platform_labels(): void
    {
        $agent = Admin::create(['name' => 'Agent', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($agent, $this->package);
        // Issued by the scheduler (system actor): not listed.
        app(PlatformBillingService::class)->generateInvoices();
        $invoice = $agent->invoices()->firstOrFail();
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.invoices.void', $invoice), ['reason' => 'Test void']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.audit.page'))
            ->assertOk()
            ->assertSee(__('platform.audit.events.invoice_voided'))
            ->assertDontSee(__('platform.audit.events.invoice_issued'));
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.audit.page', ['target_type' => 'admin_invoice']))
            ->assertOk()
            ->assertSee(__('platform.audit.targets.admin_invoice'));
    }
}
