<?php

declare(strict_types=1);

namespace App\Support\Helpers;

class QueryHelper
{
    /**
     * Escape special characters (% and _ and \) for SQL LIKE expressions.
     *
     * @param string $value Input search query string.
     * @return string Escaped string safe for LIKE queries.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Sanitize and truncate a user-supplied search term to a safe length.
     *
     * @param mixed $value Input value.
     * @param int $maxLen Maximum length (default: 100).
     * @return string Sanitized search term.
     */
    public static function sanitizeSearchTerm(mixed $value, int $maxLen = 100): string
    {
        $str = trim((string) ($value ?? ''));

        return mb_substr($str, 0, $maxLen);
    }
}
