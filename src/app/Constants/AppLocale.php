<?php

declare(strict_types=1);

namespace App\Constants;

class AppLocale
{
    public const DEFAULT = 'vi';

    public const SUPPORTED = [
        'vi' => [
            'name' => 'Tiếng Việt',
            'flag' => '🇻🇳',
            'code' => 'VN',
        ],
        'en' => [
            'name' => 'English',
            'flag' => '🇬🇧',
            'code' => 'EN',
        ],
        'ja' => [
            'name' => '日本語',
            'flag' => '🇯🇵',
            'code' => 'JA',
        ],
    ];

    /**
     * Get all supported locale codes.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::SUPPORTED);
    }

    /**
     * Check if a given locale code is supported.
     */
    public static function isValid(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    /**
     * Get meta information for a specific locale.
     *
     * @return array{name: string, flag: string}
     */
    public static function get(?string $locale): array
    {
        return self::SUPPORTED[$locale] ?? self::SUPPORTED[self::DEFAULT];
    }
}
