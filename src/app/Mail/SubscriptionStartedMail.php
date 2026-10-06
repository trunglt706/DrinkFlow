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
 * Sent when the first invoice of a self-registered Agent is paid and its package starts.
 */
class SubscriptionStartedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param Admin $admin Agent.
     * @param AdminSubscription $subscription Subscription that just started (package loaded).
     */
    public function __construct(
        public readonly Admin $admin,
        public readonly AdminSubscription $subscription,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.emails.subscription_started_subject', ['app' => config('app.name', 'DrinkFlow')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.subscription-started');
    }
}
