<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PlatformPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Mail\AdminEmailVerificationMail;
use App\Mail\AgentRegistrationMail;
use App\Mail\SubscriptionStartedMail;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\AuditLog;
use App\Models\Package;
use App\Services\Admin\AdminActivationService;
use App\Services\Billing\PlatformBillingService;
use App\Services\Room\RoomQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Public Agent registration (pay-to-activate): the account, a pending subscription with the chosen
 * billing cycle and its first invoice are created at once; the activation email lets the owner set the
 * password; the package starts only when the invoice is paid. No Superadmin approval.
 */
class AdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        config(['captcha.disable' => true]);
        Mail::fake();
        $this->package = Package::create(['code' => 'starter', 'name' => 'Starter', 'monthly_price' => 199000, 'room_limit' => 3, 'status' => 'active']);
    }

    /**
     * Valid registration form data (6 months by default, like the form).
     *
     * @param array<string, mixed> $overrides Field overrides.
     * @return array<string, mixed> Form data.
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nguyen Van A',
            'company' => 'Tea Corp',
            'email' => 'Agent@Example.test',
            'phone' => '+84 901 234 567',
            'package_id' => $this->package->id,
            'period_quantity' => 6,
            'period_unit' => 'month',
        ];
    }

    private function register(array $overrides = []): Admin
    {
        $this->post(route('admin.register'), $this->payload($overrides))->assertRedirect(route('admin.register.pending'));

        return Admin::query()->where('email', mb_strtolower((string) ($overrides['email'] ?? 'agent@example.test')))->firstOrFail();
    }

    private function firstInvoice(Admin $admin): AdminInvoice
    {
        return $admin->invoices()->oldest('id')->firstOrFail();
    }

    public function test_form_lists_active_packages_and_the_billing_cycle_without_a_password(): void
    {
        Package::create(['code' => 'hidden', 'name' => 'Hidden plan', 'monthly_price' => 1, 'room_limit' => 1, 'status' => 'inactive']);

        $this->get(route('admin.register.page'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertDontSee('Hidden plan')
            ->assertSee('name="period_quantity"', false)
            ->assertSee('value="6"', false)
            ->assertSee('<option value="month" selected', false)
            ->assertSee('<option value="year"', false)
            ->assertDontSee('name="password"', false);
    }

    public function test_registration_creates_pending_agent_subscription_invoice_and_activation_email(): void
    {
        $admin = $this->register();

        $this->assertSame(AdminStatus::Pending, $admin->status);
        $this->assertSame('Tea Corp', $admin->company);
        $this->assertSame($this->package->id, $admin->requested_package_id);
        $this->assertNotNull($admin->registered_at);
        $this->assertNull($admin->email_verified_at);

        $subscription = $admin->subscriptions()->sole();
        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertSame(6, $subscription->period_months);
        $this->assertNull($subscription->expires_at);
        // Nothing is granted before the payment.
        $this->assertSame(0, app(RoomQuotaService::class)->limit($admin));

        $invoice = $this->firstInvoice($admin);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(199000 * 6, $invoice->total);
        $this->assertSame($subscription->id, $invoice->admin_subscription_id);

        $this->assertTrue(AuditLog::query()->where('event', 'admin.registered')->where('target_id', $admin->id)->exists());
        Mail::assertQueued(AgentRegistrationMail::class, fn (AgentRegistrationMail $mail): bool => $mail->hasTo('agent@example.test')
            && $mail->invoice->is($invoice) && $mail->periodMonths === 6
            && str_contains($mail->activationUrl, '/admin/activate/'));
        Mail::assertNotQueued(AdminEmailVerificationMail::class);
    }

    public function test_yearly_cycle_bills_twelve_months_per_year(): void
    {
        $admin = $this->register(['period_quantity' => 2, 'period_unit' => 'year']);

        $this->assertSame(24, $admin->subscriptions()->sole()->period_months);
        $this->assertSame(199000 * 24, $this->firstInvoice($admin)->total);
    }

    public function test_registration_validates_input(): void
    {
        $inactive = Package::create(['code' => 'old', 'name' => 'Old', 'monthly_price' => 1, 'room_limit' => 1, 'status' => 'archived']);
        Admin::create(['name' => 'Taken', 'email' => 'taken@example.test', 'password' => 'password123', 'status' => 'active']);

        $this->post(route('admin.register'), $this->payload([
            'email' => 'taken@example.test',
            'phone' => 'abc',
            'package_id' => $inactive->id,
            'name' => '',
            'period_quantity' => 0,
            'period_unit' => 'week',
        ]))->assertSessionHasErrors(['email', 'phone', 'package_id', 'name', 'period_quantity', 'period_unit']);
        $this->post(route('admin.register'), $this->payload(['period_quantity' => 4, 'period_unit' => 'year']))
            ->assertSessionHasErrors(['period_quantity' => __('platform.registration.cycle_max')]);
        $this->post(route('admin.register'), $this->payload(['period_quantity' => 25]))->assertSessionHasErrors('period_quantity');

        $this->assertSame(1, Admin::query()->count());
        $this->assertSame(0, AdminInvoice::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_captcha_is_required_when_enabled(): void
    {
        config(['captcha.disable' => false]);

        $this->post(route('admin.register'), $this->payload())->assertSessionHasErrors('captcha');
        $this->assertSame(0, Admin::query()->count());
    }

    public function test_pending_agent_cannot_sign_in_before_activation(): void
    {
        $this->register();

        // The account has a random password nobody knows: every attempt gets the generic error.
        $this->post(route('admin.login'), ['email' => 'agent@example.test', 'password' => 'any-password-1'])
            ->assertSessionHasErrors(['email' => __('admin.invalid_credentials')]);
        $this->assertGuest('admin');
    }

    public function test_activation_sets_the_password_but_the_package_waits_for_the_payment(): void
    {
        $admin = $this->register();
        $link = app(AdminActivationService::class)->link($admin);

        $this->post($link, ['sign_in_value' => 'secret-pass-1', 'sign_in_value_confirmation' => 'secret-pass-1'])
            ->assertRedirect(route('admin.login.page'));

        $admin->refresh();
        $this->assertSame(AdminStatus::Active, $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame(SubscriptionStatus::Pending, $admin->subscriptions()->sole()->status);
        $this->assertSame(0, app(RoomQuotaService::class)->limit($admin));

        $this->post(route('admin.login'), ['email' => 'agent@example.test', 'password' => 'secret-pass-1']);
        $this->assertAuthenticatedAs($admin, 'admin');
        // Without a package the next step is paying the invoice.
        $this->get(route('admin.landing'))->assertRedirect(route('admin.billing.index'));
        $this->get(route('admin.billing.index'))->assertOk()
            ->assertSee('data-billing-awaiting-payment', false)
            ->assertSee($this->firstInvoice($admin)->number);
    }

    public function test_paying_the_first_invoice_starts_the_package_for_the_registered_cycle(): void
    {
        $admin = $this->register();
        $invoice = $this->firstInvoice($admin);
        $owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->travel(3)->days();

        app(PlatformBillingService::class)->recordPayment($invoice, $owner, $invoice->total, PlatformPaymentMethod::BankTransfer);

        $subscription = $admin->subscriptions()->sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->starts_at->isSameSecond(now()));
        $this->assertTrue($subscription->expires_at->isSameSecond(now()->addMonthsNoOverflow(6)));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertTrue($invoice->period_start->equalTo($subscription->starts_at));
        $this->assertTrue($invoice->period_end->equalTo($subscription->expires_at));
        $this->assertSame(3, app(RoomQuotaService::class)->limit($admin));
        $this->assertTrue(AuditLog::query()->where('event', 'subscription.started')->where('target_id', $admin->id)->exists());
        Mail::assertQueued(SubscriptionStartedMail::class, fn (SubscriptionStartedMail $mail): bool => $mail->hasTo('agent@example.test'));

        // The paid registration invoice already bills the first period: no duplicate is generated.
        app(PlatformBillingService::class)->generateInvoices();
        $this->assertSame(1, $admin->invoices()->count());
    }

    public function test_free_package_starts_right_away(): void
    {
        $free = Package::create(['code' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'room_limit' => 1, 'status' => 'active']);

        $admin = $this->register(['package_id' => $free->id]);

        $this->assertSame(InvoiceStatus::Paid, $this->firstInvoice($admin)->status);
        $this->assertSame(SubscriptionStatus::Active, $admin->subscriptions()->sole()->status);
    }

    public function test_tampered_or_expired_legacy_verification_links_are_refused(): void
    {
        // Registrations from before pay-to-activate keep their verification links working.
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'agent@example.test', 'password' => 'password123', 'status' => 'pending', 'registered_at' => now()]);

        $this->get(route('admin.register.verify', ['admin' => $admin->id, 'hash' => sha1('agent@example.test')]))->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('admin.register.verify', now()->addHour(), ['admin' => $admin->id, 'hash' => sha1('other@example.test')]);
        $this->get($wrongHash)->assertForbidden();
        $expired = URL::temporarySignedRoute('admin.register.verify', now()->subMinute(), ['admin' => $admin->id, 'hash' => sha1('agent@example.test')]);
        $this->get($expired)->assertForbidden();

        $this->assertNull($admin->fresh()->email_verified_at);
    }

    public function test_resend_only_mails_registrations_waiting_for_activation(): void
    {
        $admin = $this->register();
        Mail::fake();

        $this->post(route('admin.register.resend'), ['email' => 'unknown@example.test'])->assertRedirect(route('admin.register.pending'));
        Mail::assertNothingQueued();

        $this->post(route('admin.register.resend'), ['email' => 'AGENT@example.test'])
            ->assertRedirect(route('admin.register.pending'))
            ->assertSessionHas('status', __('platform.registration.verification_resent'));
        Mail::assertQueued(AgentRegistrationMail::class, 1);

        $admin->forceFill(['status' => AdminStatus::Active->value, 'email_verified_at' => now()])->save();
        Mail::fake();
        $this->post(route('admin.register.resend'), ['email' => 'agent@example.test']);
        Mail::assertNothingQueued();
    }

    public function test_pending_page_renders(): void
    {
        $this->withSession(['registered_email' => 'agent@example.test'])
            ->get(route('admin.register.pending'))
            ->assertOk()
            ->assertSee('agent@example.test')
            ->assertSee(__('platform.registration.step_review'));
    }

    public function test_login_page_links_to_registration(): void
    {
        $this->get(route('admin.login.page'))->assertOk()->assertSee(route('admin.register.page'), false);
        $this->get(route('superadmin.login.page'))->assertOk()->assertDontSee(route('admin.register.page'), false);
    }
}
