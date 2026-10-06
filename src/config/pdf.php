<?php

declare(strict_types=1);

/*
 * DrinkFlow documents rendered to PDF (App\Services\Billing\InvoicePdfService).
 * The engine itself is configured in config/laravel-pdf.php (DOMPDF by default, no Chrome needed).
 */

return [
    /*
     * TrueType font (.ttf, not .ttc) with Japanese glyphs, e.g. Noto Sans JP Regular (SIL Open Font License).
     * The bundled DejaVu Sans covers Vietnamese and English only, so without this file a Japanese
     * invoice PDF is written with English labels instead of unreadable glyphs.
     * The file must sit inside the DOMPDF chroot (storage/fonts by default).
     */
    'cjk_font_path' => env('PDF_CJK_FONT_PATH', storage_path('fonts/NotoSansJP-Regular.ttf')),
];
