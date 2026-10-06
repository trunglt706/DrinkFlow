<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Admin;
use App\Models\AdminSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent a few days before the current period ends: the renewal invoice of the next period is coming.
 */
class SubscriptionRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param Admin $admin Agent.
     * @param AdminSubscription $subscription Subscription about to renew (package loaded).
     */
    public function __construct(
        public readonly Admin $admin,
        public readonly AdminSubscription $subscription,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.emails.renewal_subject', [
            'app' => config('app.name', 'DrinkFlow'),
            'date' => $this->subscription->expires_at?->toAppDate(),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.renewal-reminder');
    }
}
