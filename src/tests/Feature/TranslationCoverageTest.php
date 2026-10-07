<?php

declare(strict_types=1);

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TranslationCoverageTest extends TestCase
{
    /** @var array<int, string> Locales every UI string must be translated into. */
    private const LOCALES = ['vi', 'en', 'ja'];

    /**
     * Every literal translation key used in code must exist in vi, en and ja.
     *
     * Keys built by concatenation (ending in "_" or ".") are skipped because their suffix is only known at runtime.
     */
    public function test_literal_translation_keys_exist_in_every_locale(): void
    {
        $missing = [];

        foreach ($this->literalKeys() as $key => $file) {
            foreach (self::LOCALES as $locale) {
                if (! Lang::has($key, $locale, false)) {
                    $missing[] = sprintf('%s [%s] (%s)', $key, $locale, $file);
                }
            }
        }

        $this->assertSame([], $missing, "Missing translations:\n".implode("\n", $missing));
    }

    /**
     * The vi, en and ja lang files must define the same keys, so no locale falls back to another language
     * and no orphan key lingers in a single locale.
     */
    public function test_every_locale_defines_the_same_keys(): void
    {
        $keysByLocale = [];
        foreach (self::LOCALES as $locale) {
            $keysByLocale[$locale] = $this->localeKeys($locale);
        }

        $drift = [];
        foreach (self::LOCALES as $locale) {
            foreach (self::LOCALES as $other) {
                if ($other === $locale) {
                    continue;
                }
                foreach (array_diff($keysByLocale[$locale], $keysByLocale[$other]) as $key) {
                    $drift[] = sprintf('%s: defined in %s, missing in %s', $key, $locale, $other);
                }
            }
        }

        $this->assertSame([], $drift, "Translation keys out of sync:\n".implode("\n", $drift));
    }

    /**
     * Flatten every lang/{locale}/*.php file into dotted keys. A list (e.g. category suggestions) counts as one key.
     *
     * @param string $locale Locale folder name.
     * @return array<int, string> Dotted keys such as "admin.default_category_other".
     */
    private function localeKeys(string $locale): array
    {
        $keys = [];
        foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
            $this->flattenKeys(require $file, basename($file, '.php'), $keys);
        }
        sort($keys);

        return $keys;
    }

    /**
     * @param array<array-key, mixed> $lines Translation lines.
     * @param string $prefix Key prefix built so far.
     * @param array<int, string> $keys Collected keys (by reference).
     */
    private function flattenKeys(array $lines, string $prefix, array &$keys): void
    {
        foreach ($lines as $key => $value) {
            $path = $prefix.'.'.$key;
            if (is_array($value) && ! array_is_list($value)) {
                $this->flattenKeys($value, $path, $keys);
            } else {
                $keys[] = $path;
            }
        }
    }

    /**
     * Collect literal keys passed to __(), trans(), @lang() or Lang::get().
     *
     * @return array<string, string> Map of translation key to the first file that uses it.
     */
    private function literalKeys(): array
    {
        $keys = [];

        foreach (['app', 'resources/views', 'resources/js', 'routes'] as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if (! preg_match_all('/(?:__|trans|@lang|Lang::get)\(\s*[\'"]([a-z_]+(?:\.[A-Za-z0-9_\-]+)+)[\'"]/', $source, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $key) {
                    if (str_ends_with($key, '_') || str_ends_with($key, '.')) {
                        continue;
                    }
                    $keys[$key] ??= str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        ksort($keys);

        return $keys;
    }
}
