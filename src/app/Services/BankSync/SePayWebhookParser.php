<?php

declare(strict_types=1);

namespace App\Services\BankSync;

use App\Models\BankTransaction;
use App\Models\RoomBankIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * SePay bank-feed webhook.
 *
 * SePay posts one JSON transaction per call — {id, gateway, transactionDate, accountNumber, subAccount, code, content,
 * transferType ("in"/"out"), transferAmount, accumulated, referenceCode, description} — with the header
 * `Authorization: Apikey {API_KEY}` configured on the SePay side. A 2xx response with {"success": true} stops retries.
 */
class SePayWebhookParser
{
    /** Timezone of SePay's `transactionDate` (Vietnamese banks). */
    private const PROVIDER_TIMEZONE = 'Asia/Ho_Chi_Minh';

    /**
     * Authenticate and parse a notification.
     *
     * @param Request $request Incoming webhook.
     * @param RoomBankIntegration $integration Room connection the webhook URL belongs to.
     * @return BankTransferData Parsed transaction.
     *
     * @throws InvalidBankWebhook When the API key is missing or wrong, or the body is incomplete.
     */
    public function parse(Request $request, RoomBankIntegration $integration): BankTransferData
    {
        $expected = (string) $integration->api_key;
        $header = trim((string) $request->header('Authorization', ''));
        $given = preg_match('/^Apikey\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : '';
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            throw new InvalidBankWebhook('Invalid SePay API key.');
        }

        $data = json_decode((string) $request->getContent(), true);
        if (! is_array($data) || ! isset($data['id'], $data['transferType'], $data['transferAmount']) || ! is_numeric($data['transferAmount'])) {
            throw new InvalidBankWebhook('Incomplete SePay notification.');
        }

        // A virtual sub-account (VA) identifies the receiving account more precisely than the main account.
        $account = ($data['subAccount'] ?? null) ?: ($data['accountNumber'] ?? null);

        return new BankTransferData(
            (string) $data['id'],
            strtolower((string) $data['transferType']) === 'in' ? BankTransaction::DIRECTION_IN : BankTransaction::DIRECTION_OUT,
            max(0, (int) $data['transferAmount']),
            mb_substr(trim((string) ($data['content'] ?? $data['description'] ?? '')), 0, 500),
            $account !== null ? mb_substr((string) $account, 0, 50) : null,
            isset($data['referenceCode']) ? mb_substr((string) $data['referenceCode'], 0, 100) : null,
            $this->parseDate($data['transactionDate'] ?? null),
            $data,
        );
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, self::PROVIDER_TIMEZONE)->setTimezone((string) config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
