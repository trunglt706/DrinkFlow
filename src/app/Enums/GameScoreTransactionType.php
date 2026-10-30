<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Entries of the points ledger of a game room: points awarded by a result, and their reversal when it is voided.
 */
enum GameScoreTransactionType: string
{
    case Award = 'award';
    case Reversal = 'reversal';
}
