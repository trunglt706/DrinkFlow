<?php

// SaaS platform settings: billing of Agent subscriptions and online payment of platform invoices.
return [
    'billing' => [
        // Days an Agent has to pay a platform invoice before it becomes overdue.
        'due_days' => (int) env('PLATFORM_INVOICE_DUE_DAYS', 7),
        // Days after the due date during which an overdue Agent keeps full access (grace period).
        'grace_days' => (int) env('PLATFORM_INVOICE_GRACE_DAYS', 7),
        // Package (code) given to Agents that have never had a subscription (accounts from before the SaaS model).
        'default_package' => env('PLATFORM_DEFAULT_PACKAGE', 'starter'),
        // Suspend Agents automatically once the grace period of an overdue invoice has passed (the Agent is
        // emailed and its managing / full-access Superadmins are notified). Set false to only flag invoices.
        'auto_suspend' => (bool) env('PLATFORM_AUTO_SUSPEND', true),
        // Days before the end of a period when the "your package renews soon" email is sent.
        'renewal_reminder_days' => (int) env('PLATFORM_RENEWAL_REMINDER_DAYS', 7),
    ],

    'payments' => [
        // Online payment of platform invoices through a hosted checkout page.
        'enabled' => (bool) env('PLATFORM_PAYMENTS_ENABLED', false),
        // Hosted checkout page of the payment provider; receives the invoice, amount and an HMAC signature.
        'checkout_url' => env('PLATFORM_PAYMENTS_CHECKOUT_URL'),
        // Shared secret signing checkout links and payment notifications (webhooks).
        'webhook_secret' => env('PLATFORM_PAYMENTS_WEBHOOK_SECRET'),
    ],
];
