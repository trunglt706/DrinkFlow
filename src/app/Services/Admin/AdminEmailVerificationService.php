<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminStatus;
use App\Mail\AdminEmailVerificationMail;
use App\Models\Admin;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Email verification of self-registered Agents.
 *
 * The link is a temporary signed URL carrying a hash of the email address, so it stops working
 * when the link expires or the address changes. A registration can only be approved once verified.
 */
class AdminEmailVerificationService
{
    /** Hours a verification link stays valid. */
    public const LINK_TTL_HOURS = 24;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Queue the verification email (sent after the surrounding transaction commits).
     *
     * @param Admin $admin Pending Agent.
     * @return void
     */
    public function send(Admin $admin): void
    {
        Mail::to($admin->email)->queue(
            (new AdminEmailVerificationMail($admin, $this->link($admin), self::LINK_TTL_HOURS))
                ->locale(app()->getLocale())
                ->afterCommit(),
        );
    }

    /**
     * Signed verification URL of the Agent.
     *
     * The signature covers the path and query only (`signed:relative` on the route), so the link keeps working
     * whichever host serves the app (localhost / 127.0.0.1, or HTTP behind an HTTPS proxy).
     *
     * @param Admin $admin Agent.
     * @return string Absolute URL.
     */
    public function link(Admin $admin): string
    {
        return url(URL::temporarySignedRoute('admin.register.verify', now()->addHours(self::LINK_TTL_HOURS), [
            'admin' => $admin->id,
            'hash' => $this->hash($admin),
        ], absolute: false));
    }

    /**
     * Whether a hash from a verification link matches the Agent's current email.
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
     * Mark the email as verified (idempotent).
     *
     * @param Admin $admin Agent.
     * @return bool True when this call verified it, false when it already was.
     */
    public function verify(Admin $admin): bool
    {
        if ($admin->hasVerifiedEmail()) {
            return false;
        }

        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->audit->record('admin.email_verified', 'admin', $admin->id);

        return true;
    }

    /**
     * Resend the link to a pending, unverified registration. Unknown or verified addresses are ignored
     * silently so the form does not reveal which emails are registered.
     *
     * @param string $email Email typed on the form.
     * @return void
     */
    public function resend(string $email): void
    {
        $admin = Admin::query()->where('email', mb_strtolower(trim($email)))->first();
        if ($admin !== null && $admin->status === AdminStatus::Pending && ! $admin->hasVerifiedEmail()) {
            $this->send($admin);
        }
    }

    /**
     * Hash of the Agent's email embedded in the link.
     *
     * @param Admin $admin Agent.
     * @return string SHA-1 of the email.
     */
    private function hash(Admin $admin): string
    {
        return sha1(mb_strtolower($admin->email));
    }
}
