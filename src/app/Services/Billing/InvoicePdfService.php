<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AdminInvoice;
use Spatie\Browsershot\Browsershot;

/**
 * Printable document of one platform invoice, as HTML and as an A4 PDF.
 *
 * The same Blade template feeds both, so the PDF and the in-app invoice view always match.
 * PDF rendering needs Node.js + puppeteer + Chrome on the server (config/pdf.php).
 */
class InvoicePdfService
{
    /**
     * HTML document of the invoice.
     *
     * @param AdminInvoice $invoice Invoice to render.
     * @return string HTML.
     */
    public function html(AdminInvoice $invoice): string
    {
        $invoice->loadMissing(['admin', 'package:id,name', 'payments' => static fn ($query) => $query->orderBy('paid_at')]);

        return view('admin.billing.invoice-document', [
            'invoice' => $invoice,
        ])->render();
    }

    /**
     * A4 PDF of the invoice.
     *
     * @param AdminInvoice $invoice Invoice to render.
     * @return string PDF bytes.
     *
     * @throws \Throwable When headless Chrome is missing or fails.
     */
    public function pdf(AdminInvoice $invoice): string
    {
        $browsershot = Browsershot::html($this->html($invoice))
            ->setNodeBinary((string) config('pdf.node_binary'))
            ->setNpmBinary((string) config('pdf.npm_binary'))
            ->setNodeModulePath((string) config('pdf.node_module_path'))
            ->timeout((int) config('pdf.timeout', 30))
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->showBackground();

        if ((string) config('pdf.chrome_path') !== '') {
            $browsershot->setChromePath((string) config('pdf.chrome_path'));
        }
        if ((bool) config('pdf.no_sandbox')) {
            $browsershot->noSandbox();
        }

        return $browsershot->pdf();
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
