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
 * Sent when the service is suspended automatically because an invoice stayed unpaid past its grace period.
 */
class AgentBillingSuspendedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param Admin $admin Suspended Agent.
     * @param AdminInvoice|null $invoice Oldest overdue invoice that caused the suspension.
     */
    public function __construct(
        public readonly Admin $admin,
        public readonly ?AdminInvoice $invoice,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.emails.billing_suspended_subject', ['app' => config('app.name', 'DrinkFlow')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.billing-suspended');
    }
}
