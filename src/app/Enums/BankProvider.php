<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bank-feed providers that notify DrinkFlow of incoming transfers.
 */
enum BankProvider: string
{
    case SePay = 'sepay';
}
