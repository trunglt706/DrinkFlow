<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PlatformPaymentMethod;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Package;
use App\Services\Admin\AdminActivationService;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A self-registered Agent stays on its billing pages until the first invoice is paid; an Agent already
 * using the platform gets a bottom reminder for its unpaid invoices.
 */
class AgentPaymentGateTest extends TestCase
{
    use RefreshDatabase;

    private const SIGN_IN_VALUE = 'secret-pass-1';

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        config(['captcha.disable' => true]);
        Mail::fake();
        $this->package = Package::create(['code' => 'starter', 'name' => 'Starter', 'monthly_price' => 199000, 'room_limit' => 3, 'status' => 'active']);
    }

    /**
     * Register and activate a self-service Agent (first invoice still unpaid), signed out.
     *
     * @return Admin Activated Agent awaiting its first payment.
     */
    private function registeredAgent(): Admin
    {
        $this->post(route('admin.register'), [
            'name' => 'Nguyen Van A', 'company' => 'Tea Corp', 'email' => 'agent@example.test', 'phone' => '+84 901 234 567',
            'package_id' => $this->package->id, 'period_quantity' => 6, 'period_unit' => 'month',
        ])->assertRedirect(route('admin.register.pending'));
        $admin = Admin::query()->where('email', 'agent@example.test')->firstOrFail();
        $this->post(app(AdminActivationService::class)->link($admin), [
            'sign_in_value' => self::SIGN_IN_VALUE, 'sign_in_value_confirmation' => self::SIGN_IN_VALUE,
        ])->assertRedirect(route('admin.login.page'));

        return $admin->refresh();
    }

    /**
     * Active Agent with a running subscription and its generated (open) invoice.
     *
     * @return array{0: Admin, 1: AdminInvoice} Agent and its open invoice.
     */
    private function activeAgentWithOpenInvoice(): array
    {
        $admin = Admin::create(['name' => 'Alice', 'email' => 'alice@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(SubscriptionService::class)->activate($admin, $this->package);
        $this->artisan('billing:generate-invoices')->assertSuccessful();

        return [$admin, $admin->invoices()->latest('id')->firstOrFail()];
    }

    public function test_new_agent_lands_on_billing_after_sign_in_even_with_an_intended_page(): void
    {
        $admin = $this->registeredAgent();

        $this->get(route('admin.rooms.index'))->assertRedirect(route('admin.login.page'));
        $this->followingRedirects()
            ->post(route('admin.login'), ['email' => 'agent@example.test', 'password' => self::SIGN_IN_VALUE])
            ->assertOk()
            ->assertSee('data-billing-awaiting-payment', false)
            ->assertSee($admin->invoices()->sole()->number);
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_new_agent_cannot_leave_the_billing_pages_until_the_first_invoice_is_paid(): void
    {
        $admin = $this->registeredAgent();
        $invoice = $admin->invoices()->sole();
        $this->actingAs($admin, 'admin');

        foreach ([route('admin.landing'), route('admin.rooms.index'), route('admin.rooms.create'), route('admin.subscription.show'), route('admin.profile')] as $url) {
            $this->get($url)->assertRedirect(route('admin.billing.index'));
        }
        $this->getJson(route('admin.rooms.index'))->assertStatus(402)
            ->assertJsonPath('message', __('platform.billing.first_payment_required'));
        $this->post(route('admin.rooms.store'), ['name' => 'Room'])->assertRedirect(route('admin.billing.index'));

        $this->get(route('admin.billing.index'))->assertOk()
            ->assertSee(__('platform.billing.first_payment_locked'))
            ->assertSee(route('admin.billing.show', $invoice), false)
            // The other account pages are shown locked in the sidebar.
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href="'.route('admin.rooms.index').'"', false)
            // No bottom reminder on top of the lock.
            ->assertDontSee('data-payment-reminder', false);
        $this->get(route('admin.billing.show', $invoice))->assertOk();
        $this->post(route('admin.logout'))->assertRedirect();
        $this->assertGuest('admin');
    }

    public function test_paying_the_first_invoice_unlocks_the_admin_area(): void
    {
        $admin = $this->registeredAgent();
        $invoice = $admin->invoices()->sole();
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(PlatformBillingService::class)->recordPayment($invoice, $owner, $invoice->total, PlatformPaymentMethod::BankTransfer);

        $this->actingAs($admin->refresh(), 'admin');
        $this->get(route('admin.rooms.index'))->assertOk()->assertDontSee('data-payment-reminder', false);
        $this->get(route('admin.subscription.show'))->assertOk();
        $this->get(route('admin.billing.index'))->assertOk()->assertDontSee('data-billing-awaiting-payment', false);
    }

    public function test_active_agent_sees_a_bottom_reminder_for_its_unpaid_invoice(): void
    {
        [$admin, $invoice] = $this->activeAgentWithOpenInvoice();
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.rooms.index'))->assertOk()
            ->assertSee('data-payment-reminder', false)
            ->assertSee(__('platform.billing.reminder_open', ['number' => $invoice->number]))
            ->assertSee(route('admin.billing.show', $invoice), false)
            ->assertDontSee(__('platform.billing.reminder_view_all'));
        // The billing pages already list the invoices.
        $this->get(route('admin.billing.index'))->assertOk()->assertDontSee('data-payment-reminder', false);
    }

    public function test_reminder_puts_an_overdue_invoice_first_and_counts_the_others(): void
    {
        [$admin, $open] = $this->activeAgentWithOpenInvoice();
        // An earlier period left unpaid (period_subscription_id is a generated column, never copied).
        $overdue = $open->replicate(['period_subscription_id']);
        $overdue->forceFill([
            'number' => $open->number.'-OLD', 'status' => InvoiceStatus::Overdue->value,
            'period_start' => $open->period_start->copy()->subMonth(), 'period_end' => $open->period_start, 'due_at' => now()->subDays(3),
        ])->save();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.rooms.index'))->assertOk()
            ->assertSee(__('platform.billing.reminder_overdue', ['number' => $overdue->number]))
            ->assertSee(route('admin.billing.show', $overdue), false)
            ->assertSee(__('platform.billing.reminder_view_all'));
    }

    public function test_no_reminder_once_every_invoice_is_paid(): void
    {
        [$admin, $invoice] = $this->activeAgentWithOpenInvoice();
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(PlatformBillingService::class)->recordPayment($invoice, $owner, $invoice->total, PlatformPaymentMethod::BankTransfer);

        $this->actingAs($admin, 'admin')->get(route('admin.rooms.index'))->assertOk()->assertDontSee('data-payment-reminder', false);
    }
}
