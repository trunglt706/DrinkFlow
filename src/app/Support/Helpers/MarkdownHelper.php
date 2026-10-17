<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Support\Str;

/**
 * Markdown written by users or superadmins (release notes, profile bio).
 */
final class MarkdownHelper
{
    /**
     * Word pattern shared with the browser counter (resources/js/shared/markdown-format.js): runs of letters or
     * digits, joined by an apostrophe or hyphen. Markdown symbols (**, _, -, #, >) are not words.
     */
    private const WORD_PATTERN = "/[\\p{L}\\p{N}]+(?:['’-][\\p{L}\\p{N}]+)*/u";

    /**
     * Render Markdown to HTML that is safe to print: raw HTML is escaped and unsafe links (javascript:, data:, …) are dropped.
     *
     * @param string $markdown Markdown source.
     * @return string Rendered HTML, empty for blank input.
     */
    public static function toSafeHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Number of words in a text (Markdown symbols ignored).
     *
     * @param string $text Text or Markdown source.
     * @return int Word count.
     */
    public static function wordCount(string $text): int
    {
        return (int) preg_match_all(self::WORD_PATTERN, $text);
    }
}
