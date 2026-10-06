<?php

declare(strict_types=1);

/*
 * spatie/laravel-pdf (downloadable documents such as the Agent's platform invoices, /admin/billing).
 * DrinkFlow renders with DOMPDF by default: pure PHP, no Node.js / Chrome on the server.
 * Switch LARAVEL_PDF_DRIVER to "browsershot" only on a server that has Node.js + puppeteer + Chrome.
 */

use Spatie\LaravelPdf\Caching\DefaultPdfCache;
use Spatie\LaravelPdf\Encryption\DefaultPdfEncrypter;
use Spatie\LaravelPdf\Jobs\GeneratePdfJob;

return [
    /*
     * The default driver to use for PDF generation.
     * Supported: "browsershot", "cloudflare", "dompdf", "gotenberg", "chrome"
     */
    'driver' => env('LARAVEL_PDF_DRIVER', 'dompdf'),

    /*
     * Render caching. When you call `->cache()` on a PDF, the generated
     * content is stored so identical renders are served from the cache.
     *
     * Swap `class` for your own implementation of the PdfCache contract
     * to fully customize how PDFs are keyed, stored, and expired.
     */
    'cache' => [
        'class' => DefaultPdfCache::class,

        /*
         * When set to true, every PDF is cached automatically without having
         * to call `->cache()`. Call `->cache()` or `->dontCache()` on a PDF
         * to override this.
         */
        'automatic' => env('LARAVEL_PDF_CACHE_AUTOMATIC', false),

        /*
         * The cache store to use. Leave null to use the default store.
         */
        'store' => env('LARAVEL_PDF_CACHE_STORE'),

        /*
         * The prefix prepended to every cache key.
         */
        'prefix' => 'laravel-pdf',

        /*
         * The default lifetime in seconds. Leave null to cache forever.
         */
        'ttl' => env('LARAVEL_PDF_CACHE_TTL', 60 * 60 * 24),
    ],

    /*
     * Browsershot driver configuration.
     *
     * Requires the spatie/browsershot package:
     * composer require spatie/browsershot
     */
    'browsershot' => [
        /*
         * Configure the paths to Node.js, npm, Chrome, and other binaries.
         * Leave null to use system defaults or Browsershot's auto-detection.
         */
        'node_binary' => env('LARAVEL_PDF_NODE_BINARY', env('PDF_NODE_BINARY')),
        'npm_binary' => env('LARAVEL_PDF_NPM_BINARY', env('PDF_NPM_BINARY')),
        'include_path' => env('LARAVEL_PDF_INCLUDE_PATH'),
        'chrome_path' => env('LARAVEL_PDF_CHROME_PATH', env('PDF_CHROME_PATH')),
        'node_modules_path' => env('LARAVEL_PDF_NODE_MODULES_PATH', env('PDF_NODE_MODULE_PATH')),
        'bin_path' => env('LARAVEL_PDF_BIN_PATH'),
        'temp_path' => env('LARAVEL_PDF_TEMP_PATH'),

        /*
         * Other Browsershot configuration options.
         */
        'write_options_to_file' => env('LARAVEL_PDF_WRITE_OPTIONS_TO_FILE', true),
        'no_sandbox' => env('LARAVEL_PDF_NO_SANDBOX', env('PDF_NO_SANDBOX', false)),
    ],

    /*
     * Cloudflare Browser Rendering driver configuration.
     *
     * Requires a Cloudflare account with the Browser Rendering API enabled.
     * https://developers.cloudflare.com/browser-rendering/
     */
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    ],

    /*
     * Gotenberg driver configuration.
     *
     * Requires a running Gotenberg instance (Docker recommended).
     * https://gotenberg.dev
     */
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://localhost:3000'),
        'username' => env('GOTENBERG_USERNAME'),
        'password' => env('GOTENBERG_PASSWORD'),
    ],

    /*
     * DOMPDF driver configuration.
     *
     * Pure PHP PDF generation — no external binaries required.
     * Requires the dompdf/dompdf package:
     * composer require dompdf/dompdf
     */
    'dompdf' => [
        /*
         * Allow DOMPDF to fetch external resources (images, CSS).
         * Set to true if your HTML references remote URLs.
         */
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),

        /*
         * The base path for local file access.
         * Defaults to DOMPDF's built-in chroot setting when null.
         */
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT', storage_path('fonts')),

        /*
         * The folder where DOMPDF stores fonts loaded via @font-face.
         * Defaults to DOMPDF's lib/fonts folder inside vendor when null.
         */
        'font_dir' => env('LARAVEL_PDF_DOMPDF_FONT_DIR', storage_path('fonts/dompdf')),

        /*
         * The folder where DOMPDF caches font metrics.
         * Defaults to font_dir when null.
         */
        'font_cache' => env('LARAVEL_PDF_DOMPDF_FONT_CACHE'),
    ],

    /*
    * WeasyPrint driver configuration.
    *
    * Requires the Weasyprint binary and pontedilana/php-weasyprint package:
    * composer require pontedilana/php-weasyprint
    *
    * @see https://doc.courtbouillon.org/weasyprint/stable/first_steps.html
    */
    'weasyprint' => [
        /*
         * Configure the paths to the Weasyprint binary.
         */
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', 'weasyprint'),

        /*
         * The timeout (default = 10 seconds)
         */
        'timeout' => 10,
    ],

    /*
     * Chrome PHP driver configuration.
     *
     * Requires the Chrome/Chromium executable and chrome-php/chrome package:
     * composer require chrome-php/chrome
     *
     * @see https://github.com/chrome-php/chrome
     */
    'chrome' => [
        'chrome_binary' => env('LARAVEL_PDF_CHROME_BINARY'),

        'no_sandbox' => env('LARAVEL_PDF_CHROME_NO_SANDBOX', false),

        'startup_timeout' => env('LARAVEL_PDF_CHROME_STARTUP_TIMEOUT', 30),

        'timeout' => env('LARAVEL_PDF_CHROME_TIMEOUT', 30000),

        'operation_timeout' => env('LARAVEL_PDF_CHROME_OPERATION_TIMEOUT', 5000),

        'user_data_dir' => env('LARAVEL_PDF_CHROME_USER_DATA_DIR'),

        'custom_flags' => [],

        'env_variables' => [],
    ],

    /*
     * The job class used for queued PDF generation.
     * You can replace this with your own class that extends GeneratePdfJob
     * to customize things like $tries, $timeout, $backoff, or default queue.
     */
    'job' => GeneratePdfJob::class,

    /*
     * The class used to encrypt and decrypt password-protected PDFs.
     *
     * More info in our docs:
     * https://spatie.be/docs/laravel-pdf/v2/basic-usage/protecting-pdfs-with-a-password
     */
    'encrypter' => DefaultPdfEncrypter::class,
];
