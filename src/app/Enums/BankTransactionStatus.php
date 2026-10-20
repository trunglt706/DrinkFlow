<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reconciliation state of an incoming bank transaction.
 */
enum BankTransactionStatus: string
{
    /** Matched automatically and the debt / payment request was settled. */
    case Matched = 'matched';

    /** Assigned to a debt by the room admin. */
    case Manual = 'manual';

    /** Waiting for the admin: no code found, ambiguous, or the amount does not match. */
    case Unmatched = 'unmatched';

    /** Not a member payment (outgoing, another account) or dismissed by the admin. */
    case Ignored = 'ignored';
}
