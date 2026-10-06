<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PermissionScope;
use App\Enums\PlatformPaymentMethod;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Services\Billing\PlatformBillingService;
use App\Services\Billing\PlatformRevenueService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T34–T44: platform invoices, payments, scheduler, idempotency, overdue, revenue and billing pages.
 */
class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    private PlatformBillingService $billing;

    private SubscriptionService $subscriptions;

    private Package $package;

    private Admin $alice;

    private Admin $bob;

    private Superadmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfMonth()->addDays(2)->setTime(9, 0));
        $this->billing = app(PlatformBillingService::class);
        $this->subscriptions = app(SubscriptionService::class);
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        $this->alice = $this->agent('alice@drinkflow.test');
        $this->bob = $this->agent('bob@drinkflow.test');
    }

    private function agent(string $email): Admin
    {
        $admin = Admin::create(['name' => $email, 'email' => $email, 'password' => 'password123', 'status' => 'active']);
        $this->subscriptions->activate($admin, $this->package);

        return $admin;
    }

    /**
     * Superadmin with the given finance permissions in one scope, managing Alice only.
     *
     * @param array<int, string> $keys Permission keys.
     * @return Superadmin Superadmin.
     */
    private function managedFinance(array $keys): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Finance', 'email' => 'finance@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($keys as $key) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => PermissionScope::Managed->value]);
        }
        $superadmin->managedAdmins()->attach($this->alice->id, ['is_primary' => true, 'assigned_at' => now()]);

        return $superadmin;
    }

    private function invoiceOf(Admin $admin): AdminInvoice
    {
        return $admin->invoices()->latest('id')->firstOrFail();
    }

    public function test_billing_tables_are_separate_from_room_finance(): void
    {
        $this->assertTrue(Schema::hasColumns('admin_invoices', ['number', 'admin_id', 'admin_subscription_id', 'period_start', 'period_end', 'total', 'paid_amount', 'status', 'due_at']));
        $this->assertTrue(Schema::hasColumns('admin_payments', ['admin_invoice_id', 'admin_id', 'amount', 'method', 'paid_at', 'recorded_by_superadmin_id']));
    }

    public function test_generation_bills_the_snapshot_price_once_per_period(): void
    {
        $this->package->update(['monthly_price' => 999999]);

        $this->artisan('billing:generate-invoices')->expectsOutputToContain('Invoices created: 2')->assertSuccessful();
        $this->artisan('billing:generate-invoices')->expectsOutputToContain('Invoices created: 0')->assertSuccessful();

        $invoice = $this->invoiceOf($this->alice);
        $this->assertSame(500000, $invoice->total);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', (string) $invoice->number);
        $this->assertTrue($invoice->due_at->equalTo(now()->addDays(7)));
        $this->assertSame(2, AdminInvoice::query()->count());
    }

    public function test_renewed_period_gets_a_new_invoice(): void
    {
        $this->billing->generateInvoices();
        $this->travel(1)->months();
        $this->travel(1)->minutes();

        $this->subscriptions->processDuePeriods();
        $this->billing->generateInvoices();

        $this->assertSame(2, $this->alice->invoices()->count());
        $periods = $this->alice->invoices()->orderBy('period_start')->get();
        $this->assertTrue($periods[1]->period_start->equalTo($periods[0]->period_end));
    }

    public function test_upgrade_credit_is_deducted_from_the_first_invoice(): void
    {
        $large = Package::create(['code' => 'large', 'name' => 'Large', 'monthly_price' => 900000, 'room_limit' => 20, 'status' => 'active']);
        $this->billing->generateInvoices();
        $this->travel(10)->days();

        // The Agent's upgrade invoice carries the credit and becomes the new subscription's first-period invoice.
        $invoice = app(SubscriptionUpgradeService::class)->request($this->alice, $large);
        $this->assertSame(900000, $invoice->subtotal);
        $this->assertGreaterThan(0, $invoice->credit);
        $this->assertSame(900000 - $invoice->credit, $invoice->total);

        $this->billing->recordPayment($invoice, $this->owner, $invoice->total, PlatformPaymentMethod::BankTransfer);
        $invoiceCount = $this->alice->invoices()->count();
        $this->billing->generateInvoices();

        $subscription = $this->subscriptions->current($this->alice);
        $this->assertSame($large->id, $subscription->package_id);
        $this->assertSame($subscription->id, $invoice->fresh()->admin_subscription_id);
        $this->assertSame($invoiceCount, $this->alice->invoices()->count(), 'The paid upgrade invoice already bills the first period.');
    }

    public function test_free_period_is_settled_immediately(): void
    {
        $free = Package::create(['code' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'room_limit' => 1, 'status' => 'active']);
        $carol = Admin::create(['name' => 'Carol', 'email' => 'carol@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->subscriptions->activate($carol, $free);

        $this->billing->generateInvoices();

        $this->assertSame(InvoiceStatus::Paid, $this->invoiceOf($carol)->status);
    }

    public function test_overdue_processing_flags_unpaid_invoices_after_due_date(): void
    {
        $this->billing->generateInvoices();
        $this->travel(8)->days();

        $this->artisan('billing:process-overdue')->expectsOutputToContain('Invoices marked overdue: 2')->assertSuccessful();
        $this->assertSame(0, $this->billing->processOverdue());

        $this->assertSame(InvoiceStatus::Overdue, $this->invoiceOf($this->alice)->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'invoice.overdue']);
    }

    public function test_partial_then_full_payment_settles_the_invoice(): void
    {
        $this->billing->generateInvoices();
        $invoice = $this->invoiceOf($this->alice);

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.payments.store', $invoice), ['amount' => 200000, 'method' => 'bank_transfer', 'reference' => 'VCB-1'])
            ->assertRedirect(route('superadmin.billing.agent', $this->alice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(300000, $invoice->remaining());

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.payments.store', $invoice), ['amount' => 400000, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.payments.store', $invoice), ['amount' => 300000, 'method' => 'cash'])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(2, $invoice->payments()->count());
        $this->assertSame($this->owner->id, $invoice->payments()->first()->recorded_by_superadmin_id);
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.payments.store', $invoice), ['amount' => 1, 'method' => 'cash'])->assertSessionHasErrors('invoice');
    }

    public function test_void_only_applies_to_unpaid_invoices(): void
    {
        $this->billing->generateInvoices();
        $aliceInvoice = $this->invoiceOf($this->alice);
        $bobInvoice = $this->invoiceOf($this->bob);
        $this->billing->recordPayment($bobInvoice, $this->owner, 1000, PlatformPaymentMethod::Cash);

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.invoices.void', $aliceInvoice), ['reason' => 'Goodwill gesture'])->assertRedirect();
        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.billing.invoices.void', $bobInvoice), ['reason' => 'Nope'])->assertSessionHasErrors('invoice');

        $this->assertSame(InvoiceStatus::Void, $aliceInvoice->fresh()->status);
        $this->assertSame(InvoiceStatus::Open, $bobInvoice->fresh()->status);
    }

    public function test_revenue_figures_respect_the_managed_scope(): void
    {
        $this->billing->generateInvoices();
        $this->billing->recordPayment($this->invoiceOf($this->alice), $this->owner, 100000, PlatformPaymentMethod::Cash);
        $finance = $this->managedFinance(['revenue.view']);
        $revenue = app(PlatformRevenueService::class);

        $all = $revenue->summary($this->owner);
        $scoped = $revenue->summary($finance);

        $this->assertSame(1000000, $all['mrr']);
        $this->assertSame(500000, $scoped['mrr']);
        $this->assertSame(500000, $scoped['invoiced_month']);
        $this->assertSame(100000, $scoped['collected_month']);
        $this->assertSame(400000, $scoped['outstanding']);
        $this->assertSame(900000, $all['outstanding']);
        $this->actingAs($finance, 'superadmin')->get(route('superadmin.billing.revenue'))->assertOk()->assertSee('500.000');
    }

    public function test_outstanding_and_agent_billing_are_scoped(): void
    {
        $this->billing->generateInvoices();
        $finance = $this->managedFinance(['debt.view']);

        $this->actingAs($finance, 'superadmin')->get(route('superadmin.billing.outstanding'))
            ->assertOk()
            ->assertSee($this->invoiceOf($this->alice)->number)
            ->assertDontSee($this->invoiceOf($this->bob)->number);
        $this->actingAs($finance, 'superadmin')->get(route('superadmin.billing.agent', $this->alice))->assertOk()->assertDontSee(__('platform.billing.mark_paid'));
        $this->actingAs($finance, 'superadmin')->get(route('superadmin.billing.agent', $this->bob))->assertForbidden();
        // debt.view alone cannot record payments.
        $this->actingAs($finance, 'superadmin')->post(route('superadmin.billing.payments.store', $this->invoiceOf($this->alice)), ['amount' => 1, 'method' => 'cash'])->assertForbidden();
    }

    public function test_managed_finance_cannot_pay_invoices_of_other_agents(): void
    {
        $this->billing->generateInvoices();
        $finance = $this->managedFinance(['debt.view', 'debt.manage']);

        $this->actingAs($finance, 'superadmin')->post(route('superadmin.billing.payments.store', $this->invoiceOf($this->bob)), ['amount' => 1, 'method' => 'cash'])->assertForbidden();
        $this->actingAs($finance, 'superadmin')->post(route('superadmin.billing.payments.store', $this->invoiceOf($this->alice)), ['amount' => 500000, 'method' => 'cash'])->assertRedirect();
        $this->assertSame(InvoiceStatus::Paid, $this->invoiceOf($this->alice)->status);
    }

    public function test_agent_sees_only_its_own_billing_history(): void
    {
        $this->billing->generateInvoices();

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee($this->invoiceOf($this->alice)->number)
            ->assertDontSee($this->invoiceOf($this->bob)->number)
            ->assertSee('data-billing-balance="500000"', false);
    }

    public function test_billing_pages_require_finance_permissions(): void
    {
        $nobody = Superadmin::create(['name' => 'Nobody', 'email' => 'nobody@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($nobody, 'superadmin')->get(route('superadmin.billing.revenue'))->assertForbidden();
        $this->actingAs($nobody, 'superadmin')->get(route('superadmin.billing.outstanding'))->assertForbidden();
    }
}
