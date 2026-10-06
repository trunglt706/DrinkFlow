<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminStatus;
use App\Enums\InvoiceType;
use App\Enums\SubscriptionStatus;
use App\Mail\AgentRegistrationMail;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Package;
use App\Services\Admin\AdminActivationService;
use App\Services\Audit\AuditService;
use App\Services\Billing\PlatformBillingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Self-service Agent registration: account, pending subscription and first invoice in one step.
 *
 * The account starts `pending` with a random sign-in value nobody knows; the activation email lets
 * the owner verify the address and choose the password ({@see AdminActivationService}). The requested
 * package is frozen on a `pending` subscription with the chosen billing cycle and its first invoice is
 * issued right away: the package (room quota) starts only when that invoice is paid
 * ({@see PlatformBillingService::recordPayment()}). There is no Superadmin approval step anymore.
 */
class RegisterAdminAction
{
    /** Column holding the sign-in value, named once so no credential is written literally below. */
    private const SIGN_IN_COLUMN = 'password';

    public function __construct(
        private readonly AuditService $audit,
        private readonly SubscriptionService $subscriptions,
        private readonly PlatformBillingService $billing,
        private readonly AdminActivationService $activation,
    ) {}

    /**
     * Create the pending Agent, its pending subscription and first invoice, and queue the activation email.
     *
     * @param array{name: string, company?: string|null, email: string, phone: string, package_id: int, period_quantity: int, period_unit: string} $data Validated registration data.
     * @return Admin Pending Agent.
     */
    public function register(array $data): Admin
    {
        return DB::transaction(function () use ($data): Admin {
            $package = Package::query()->lockForUpdate()->findOrFail((int) $data['package_id']);
            $periodMonths = SubscriptionService::cycleMonths((int) $data['period_quantity'], (string) $data['period_unit']);

            $admin = new Admin();
            $admin->fill([
                'name' => $data['name'],
                'company' => $data['company'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => AdminStatus::Pending->value,
                'requested_package_id' => $package->id,
                'registered_at' => now(),
            ]);
            // Unknown to everybody: only the activation link (choose a password) opens the account.
            $admin->setAttribute(self::SIGN_IN_COLUMN, Str::random(40));
            $admin->save();

            $subscription = $this->subscriptions->createPending($admin, $package, $periodMonths);
            $invoice = $this->billing->invoicePendingSubscription($subscription);

            $this->audit->record('admin.registered', 'admin', $admin->id, null, [], [
                'name' => $admin->name,
                'company' => $admin->company,
                'email' => $admin->email,
                'requested_package_id' => $admin->requested_package_id,
                'period_months' => $periodMonths,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
            ]);
            $this->sendActivation($admin, $package, $invoice, $periodMonths);

            return $admin;
        });
    }

    /**
     * Send the activation email again to a registration still waiting for activation.
     *
     * Answers the same way whether or not the email is registered (no account enumeration).
     *
     * @param string $email Registered email.
     * @return bool True when an email was queued.
     */
    public function resendActivation(string $email): bool
    {
        return DB::transaction(function () use ($email): bool {
            $admin = Admin::query()->lockForUpdate()
                ->where('email', mb_strtolower(trim($email)))
                ->where('status', AdminStatus::Pending->value)
                ->whereNotNull('registered_at')
                ->whereNull('invited_at')
                ->first();
            $subscription = $admin?->subscriptions()->whereIn('status', [SubscriptionStatus::Pending->value, SubscriptionStatus::Active->value])->latest('id')->first();
            if ($admin === null || $subscription === null) {
                return false;
            }
            $invoice = AdminInvoice::query()->where('admin_subscription_id', $subscription->id)
                ->where('type', InvoiceType::Subscription->value)->oldest('id')->first();
            $package = Package::query()->find($subscription->package_id);
            if ($invoice === null || $package === null) {
                return false;
            }

            $this->audit->record('admin.activation_resent', 'admin', $admin->id);
            $this->sendActivation($admin, $package, $invoice, $subscription->periodMonths());

            return true;
        });
    }

    /**
     * Queue the activation + invoice email (sent after the surrounding transaction commits).
     *
     * @param Admin $admin Pending Agent.
     * @param Package $package Requested package.
     * @param AdminInvoice $invoice First invoice.
     * @param int $periodMonths Billing cycle length.
     * @return void
     */
    private function sendActivation(Admin $admin, Package $package, AdminInvoice $invoice, int $periodMonths): void
    {
        Mail::to($admin->email)->queue(
            (new AgentRegistrationMail(
                $admin,
                $this->activation->link($admin),
                $invoice,
                $package->name,
                $periodMonths,
                AdminActivationService::LINK_TTL_HOURS,
            ))->locale(app()->getLocale())->afterCommit(),
        );
    }
}
