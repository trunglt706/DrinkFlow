<?php

declare(strict_types=1);

namespace App\Support\Privacy;

final class PersonalDataMasker
{
    /**
     * Keep enough of an email address for recognition without exposing the full identifier.
     */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') {
            return null;
        }

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * Keep the dialing prefix and final three digits while hiding the identifying middle digits.
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        if (mb_strlen($digits) <= 5) {
            return str_repeat('*', mb_strlen($digits));
        }

        return mb_substr($digits, 0, 2)
            .str_repeat('*', max(3, mb_strlen($digits) - 5))
            .mb_substr($digits, -3);
    }
}
