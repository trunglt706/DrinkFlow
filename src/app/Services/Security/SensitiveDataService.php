<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Superadmin;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Personal data (emails, phone numbers) in the Superadmin console: shown masked, revealed one value at a time.
 *
 * Pages and APIs never carry the real value: they get the masked text plus a reveal token, an encrypted
 * envelope (APP_KEY) holding the value, its type, the Superadmin it was issued to and an expiry. The
 * value comes back only through the reveal endpoint, for that same Superadmin, before the expiry; every
 * reveal is audited by the caller (without the value).
 */
class SensitiveDataService
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    /** Minutes a reveal token stays valid (a page left open longer must be reloaded). */
    public const TOKEN_TTL_MINUTES = 240;

    /** JSON keys masked in Superadmin API responses. */
    public const JSON_KEYS = [self::TYPE_EMAIL, self::TYPE_PHONE];

    /**
     * Masked display of a value: "al***@d***.test", "*******567".
     *
     * @param string|null $value Real value.
     * @param string $type {@see TYPE_EMAIL} or {@see TYPE_PHONE}.
     * @return string Masked text ('' for an empty value).
     */
    public function mask(?string $value, string $type): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if ($type === self::TYPE_EMAIL && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $dot = strrpos($domain, '.');
            $host = $dot === false ? $domain : substr($domain, 0, $dot);
            $tld = $dot === false ? '' : substr($domain, $dot);

            return mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1))).'***@'.mb_substr($host, 0, 1).'***'.$tld;
        }

        $visible = 3;
        $length = mb_strlen($value);

        return $length <= $visible ? str_repeat('*', $length) : str_repeat('*', $length - $visible).mb_substr($value, -$visible);
    }

    /**
     * Reveal token of a value for one Superadmin.
     *
     * @param string $value Real value.
     * @param string $type Value type.
     * @param Superadmin $viewer Superadmin allowed to reveal it.
     * @return string Encrypted token.
     */
    public function token(string $value, string $type, Superadmin $viewer): string
    {
        return Crypt::encryptString(json_encode([
            'v' => $value,
            't' => $type,
            's' => (int) $viewer->id,
            'e' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Read a reveal token back.
     *
     * @param string $token Token from the page.
     * @param Superadmin $viewer Superadmin asking.
     * @return array{value: string, type: string}|null The value, or null when the token is invalid,
     *                                                  expired or issued to another Superadmin.
     */
    public function reveal(string $token, Superadmin $viewer): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }
        if (! is_array($payload) || ! isset($payload['v'], $payload['t'], $payload['s'], $payload['e'])
            || (int) $payload['s'] !== (int) $viewer->id || (int) $payload['e'] < now()->getTimestamp()) {
            return null;
        }

        return ['value' => (string) $payload['v'], 'type' => (string) $payload['t']];
    }

    /**
     * Mask every email / phone key of an API payload, adding "{key}_reveal" tokens next to them.
     *
     * @param mixed $data Decoded JSON payload.
     * @param Superadmin $viewer Superadmin receiving it.
     * @return mixed Payload with masked values.
     */
    public function protectPayload(mixed $data, Superadmin $viewer): mixed
    {
        if (! is_array($data)) {
            return $data;
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::JSON_KEYS, true) && is_string($value) && $value !== '') {
                $data[$key] = $this->mask($value, $key);
                $data[$key.'_reveal'] = $this->token($value, $key, $viewer);
            } elseif (is_array($value)) {
                $data[$key] = $this->protectPayload($value, $viewer);
            }
        }

        return $data;
    }
}
