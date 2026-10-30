<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A result is never deleted: a wrong one is voided (with a reason) and drops out of the statistics.
 */
enum GameResultStatus: string
{
    case Valid = 'valid';
    case Voided = 'voided';
}
