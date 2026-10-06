<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an Agent subscription.
 *
 * `active` is the current subscription (at most one per Agent, enforced by a unique index);
 * `superseded` rows were replaced by a package change, `expired` ones ran out, and `cancelled`
 * ones were stopped before their end.
 */
enum SubscriptionStatus: string
{
    /** Self-registered Agent waiting for its first invoice to be paid; becomes `active` once settled. */
    case Pending = 'pending';
    case Active = 'active';
    case Superseded = 'superseded';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
