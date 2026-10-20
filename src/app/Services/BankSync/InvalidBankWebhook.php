<?php

declare(strict_types=1);

namespace App\Services\BankSync;

use RuntimeException;

/**
 * A bank webhook that is not authenticated or not well formed; nothing is recorded.
 */
final class InvalidBankWebhook extends RuntimeException
{
}
