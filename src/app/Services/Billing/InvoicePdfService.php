<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AdminInvoice;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Printable document of one platform invoice, as HTML and as an A4 PDF.
 *
 * The same Blade template feeds both, so the PDF and the in-app invoice view always match.
 * PDFs are rendered by spatie/laravel-pdf (config/laravel-pdf.php): DOMPDF by default, which is
 * pure PHP and needs no Node.js / Chrome on the server.
 */
class InvoicePdfService
{
    /** Locales whose text needs a CJK font that DOMPDF does not bundle (config/pdf.php). */
    private const CJK_LOCALES = ['ja'];

    /**
     * HTML document of the invoice.
     *
     * @param AdminInvoice $invoice Invoice to render.
     * @param string|null $cjkFontPath TrueType font embedded for Japanese text in the PDF; null in the browser view.
     * @return string HTML.
     */
    public function html(AdminInvoice $invoice, ?string $cjkFontPath = null): string
    {
        $invoice->loadMissing(['admin', 'package:id,name', 'payments' => static fn ($query) => $query->orderBy('paid_at')]);

        return view('admin.billing.invoice-document', [
            'invoice' => $invoice,
            'cjkFontPath' => $cjkFontPath,
        ])->render();
    }

    /**
     * A4 PDF of the invoice.
     *
     * A Japanese invoice embeds the configured CJK font; when that font is not installed the PDF is written
     * with English labels, because DOMPDF would otherwise print unreadable glyphs.
     *
     * @param AdminInvoice $invoice Invoice to render.
     * @return string PDF bytes.
     *
     * @throws \Throwable When the PDF engine fails.
     */
    public function pdf(AdminInvoice $invoice): string
    {
        $locale = App::getLocale();
        $cjkFont = null;
        if (in_array($locale, self::CJK_LOCALES, true)) {
            $configured = (string) config('pdf.cjk_font_path');
            $cjkFont = $configured !== '' && is_file($configured) ? $configured : null;
            if ($cjkFont === null) {
                App::setLocale('en');
            }
        }

        try {
            if (config('laravel-pdf.driver') === 'dompdf') {
                // DOMPDF caches the metrics of @font-face fonts here; it must exist and stay out of vendor/.
                File::ensureDirectoryExists((string) config('laravel-pdf.dompdf.font_dir'));
            }

            return Pdf::html($this->html($invoice, $cjkFont))
                ->format('a4')
                ->margins(12, 12, 12, 12)
                ->generatePdfContent();
        } finally {
            App::setLocale($locale);
        }
    }

    /**
     * Download file name of the invoice, e.g. `drinkflow-invoice-INV-2026-0001.pdf`.
     *
     * @param AdminInvoice $invoice Invoice.
     * @return string File name (safe characters only).
     */
    public function fileName(AdminInvoice $invoice): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($invoice->number ?: $invoice->id));

        return 'drinkflow-invoice-'.$number.'.pdf';
    }
}
