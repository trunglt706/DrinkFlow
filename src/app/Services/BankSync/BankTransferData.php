<?php

declare(strict_types=1);

namespace App\Services\BankSync;

use Illuminate\Support\Carbon;

/**
 * Provider-neutral bank transaction parsed from a webhook.
 */
final class BankTransferData
{
    /**
     * @param array<string, mixed> $payload Raw notification body (kept for the admin's audit trail).
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $direction,
        public readonly int $amount,
        public readonly string $content,
        public readonly ?string $accountNumber,
        public readonly ?string $referenceCode,
        public readonly ?Carbon $transactedAt,
        public readonly array $payload,
    ) {
    }
}
