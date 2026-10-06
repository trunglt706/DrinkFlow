<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Admin;
use App\Models\AdminInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent right after a self-service registration: the activation link (verifies the email and sets the
 * password) and the first invoice, whose payment starts the package.
 */
class AgentRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param Admin $admin Registered Agent.
     * @param string $activationUrl Signed activation link (choose the password).
     * @param AdminInvoice $invoice First invoice of the subscription.
     * @param string $packageName Requested package.
     * @param int $periodMonths Billing cycle length in months.
     * @param int $validityHours Hours the activation link stays valid.
     */
    public function __construct(
        public readonly Admin $admin,
        public readonly string $activationUrl,
        public readonly AdminInvoice $invoice,
        public readonly string $packageName,
        public readonly int $periodMonths,
        public readonly int $validityHours,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.emails.registration_subject', ['app' => config('app.name', 'DrinkFlow')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.registration');
    }
}
