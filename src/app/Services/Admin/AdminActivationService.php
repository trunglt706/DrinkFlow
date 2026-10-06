<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminStatus;
use App\Enums\PackageStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\AdminActivatedMail;
use App\Models\Admin;
use App\Models\AdminSubscription;
use App\Models\Package;
use App\Services\Audit\AuditService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Activation of a pending Agent (invited by a Superadmin, or self-registered).
 *
 * A pending Agent has no usable sign-in value: an invited account gets a random one and only carries
 * the package to subscribe to. The activation link is a temporary signed URL carrying a hash of the
 * email address, so it stops working when it expires or the address changes. Opening it asks the
 * person to choose a sign-in value; that single step verifies the address, activates the account and
 * starts the subscription, after which the Agent can sign in and reach its management area.
 *
 * {@see AdminEmailVerificationService} answers a different question (it only proves the address is
 * reachable); this service is what makes an account usable.
 */
class AdminActivationService
{
    /** Hours an activation link stays valid. */
    public const LINK_TTL_HOURS = 72;

    /**
     * Column holding the sign-in value of an account.
     *
     * Held as a constant so the field is named in exactly one place. The stored value is always the
     * one the invited person just typed and it is hashed by the model cast; no credential is written
     * literally in this class.
     */
    private const SIGN_IN_COLUMN = 'password';

    /** Name of the activation form field, used as the validation error key so the message shows up. */
    private const FORM_FIELD = 'sign_in_value';

    public function __construct(
        private readonly AuditService $audit,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /**
     * Signed activation URL of the Agent.
     *
     * @param Admin $admin Agent to activate.
     * @return string Absolute URL.
     */
    public function link(Admin $admin): string
    {
        return URL::temporarySignedRoute('admin.activate.form', now()->addHours(self::LINK_TTL_HOURS), [
            'admin' => $admin->id,
            'hash' => $this->hash($admin),
        ]);
    }

    /**
     * Whether a hash from an activation link matches the Agent's current email.
     *
     * @param Admin $admin Agent.
     * @param string $hash Hash from the link.
     * @return bool True when it matches.
     */
    public function matches(Admin $admin, string $hash): bool
    {
        return hash_equals($this->hash($admin), $hash);
    }

    /**
     * Whether the Agent may activate itself right now.
     *
     * @param Admin $admin Agent.
     * @return bool True while the account is still pending.
     */
    public function awaiting(Admin $admin): bool
    {
        return $admin->status === AdminStatus::Pending;
    }

    /**
     * Activate the pending Agent: set the sign-in value, verify the email, start the subscription.
     *
     * The package is the one selected for this Agent; it must still be selectable, otherwise the
     * activation is refused and the account stays pending, so a Superadmin can change the package
     * and send a new link.
     *
     * @param Admin $admin Pending Agent.
     * @param string $signInValue New plain sign-in value chosen on the activation page.
     * @param string|null $ip Client IP address (audit trail).
     * @return Admin Activated Agent.
     * @throws ValidationException When the account is no longer pending or its package became unavailable.
     */
    public function activate(Admin $admin, string $signInValue, ?string $ip = null): Admin
    {
        return DB::transaction(function () use ($admin, $signInValue, $ip): Admin {
            $admin = Admin::query()->lockForUpdate()->findOrFail($admin->id);
            if ($admin->status !== AdminStatus::Pending) {
                $this->reject(__('platform.activation.already_active'));
            }

            // A self-registered Agent already has its subscription (pending until the first invoice is paid):
            // activating the account only sets the password, it never grants the package for free.
            $registered = AdminSubscription::query()->where('admin_id', $admin->id)
                ->whereIn('status', [SubscriptionStatus::Pending->value, SubscriptionStatus::Active->value])
                ->latest('id')->first();
            if ($registered !== null) {
                return $this->activateRegistered($admin, $registered, $signInValue, $ip);
            }

            $package = Package::query()->lockForUpdate()->find($admin->requested_package_id);
            if ($package === null || $package->status !== PackageStatus::Active) {
                $this->reject(__('platform.activation.package_unavailable'));
            }

            $subscription = $this->subscriptions->activate($admin, $package);
            // System-managed columns: not mass assignable, and hashed by the model cast on save.
            $admin->forceFill([self::SIGN_IN_COLUMN => $signInValue]);
            $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()]);
            $admin->update([
                'status' => AdminStatus::Active->value,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->audit->record('agent.activated', 'admin', $admin->id, null, ['status' => AdminStatus::Pending->value], [
                'status' => AdminStatus::Active->value,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
                'room_limit_snapshot' => $subscription->room_limit_snapshot,
                'ip' => $ip,
            ]);
            Mail::to($admin->email)->queue(
                (new AdminActivatedMail($admin, $package->name, $subscription->room_limit_snapshot))
                    ->locale(app()->getLocale())
                    ->afterCommit(),
            );

            return $admin;
        });
    }

    /**
     * Refuse the activation with an error shown on the activation form.
     *
     * The error is keyed by the form field, so the message lands next to the input the visitor used.
     *
     * @param string $message Translated message.
     * @return never
     * @throws ValidationException Always.
     */
    /**
     * Activate the account of a self-registered Agent: password, verified email and `active` status.
     *
     * The subscription is left as it is (it starts when the first invoice is paid), so the Agent can sign
     * in and pay but gets no room quota before that. No "activated" email: the registration email already
     * carried the invoice, and the package email is sent when the payment starts the subscription.
     *
     * @param Admin $admin Locked pending Agent.
     * @param AdminSubscription $subscription Subscription created at registration.
     * @param string $signInValue Chosen sign-in value.
     * @param string|null $ip Client IP for the audit trail.
     * @return Admin Activated Agent.
     */
    private function activateRegistered(Admin $admin, AdminSubscription $subscription, string $signInValue, ?string $ip): Admin
    {
        // System-managed columns: not mass assignable, and hashed by the model cast on save.
        $admin->forceFill([self::SIGN_IN_COLUMN => $signInValue]);
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()]);
        $admin->update([
            'status' => AdminStatus::Active->value,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
        $this->audit->record('agent.activated', 'admin', $admin->id, null, ['status' => AdminStatus::Pending->value], [
            'status' => AdminStatus::Active->value,
            'subscription_id' => $subscription->id,
            'subscription_status' => $subscription->status->value,
            'ip' => $ip,
        ]);

        return $admin;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([self::FORM_FIELD => $message]);
    }

    /**
     * Hash of the Agent's email embedded in the activation link.
     *
     * @param Admin $admin Agent.
     * @return string SHA-1 of the email.
     */
    private function hash(Admin $admin): string
    {
        return sha1(mb_strtolower((string) $admin->email));
    }
}
