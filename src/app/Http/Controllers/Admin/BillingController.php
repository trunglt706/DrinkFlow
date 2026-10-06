<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The Agent's platform billing history (/admin/billing): its own invoices and payments only.
 */
class BillingController extends Controller
{
    /**
     * Invoices, payments and the amount still owed.
     *
     * @param Request $request Incoming request.
     * @param PaymentGateway $gateway Payment gateway (online payment button).
     * @param InvoicePdfService $documents Invoice documents (download file names).
     * @return View Billing history.
     */
    public function index(Request $request, PaymentGateway $gateway, InvoicePdfService $documents): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('admin.billing.index', [
            'documents' => $documents,
            // Self-registered Agent whose first invoice is unpaid: the package starts with the payment.
            'awaitingFirstPayment' => $admin->subscriptions()->pending()->exists() && ! $admin->subscriptions()->active()->exists(),
            'invoices' => $admin->invoices()->with('package:id,name')->latest('period_start')->latest('id')->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE),
            'payments' => $admin->platformPayments()->with('invoice:id,number')->latest('paid_at')->limit(50)->get(),
            'balance' => (int) $admin->invoices()->outstanding()->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due'),
            'onlinePayment' => $gateway->isEnabled(),
        ]);
    }

    /**
     * View one of the Agent's own invoices inside the admin area (the same document as the PDF).
     *
     * @param Request $request Incoming request.
     * @param AdminInvoice $invoice Invoice (404 when it belongs to another Agent).
     * @param InvoicePdfService $documents Invoice document renderer.
     * @param PaymentGateway $gateway Payment gateway (online payment button).
     * @return View Invoice page.
     */
    public function show(Request $request, AdminInvoice $invoice, InvoicePdfService $documents, PaymentGateway $gateway): View
    {
        abort_unless((int) $invoice->admin_id === (int) $request->user('admin')->id, 404);

        return view('admin.billing.show', [
            'invoice' => $invoice->loadMissing('package:id,name'),
            'document' => $documents->html($invoice),
            'fileName' => $documents->fileName($invoice),
            'onlinePayment' => $gateway->isEnabled() && $invoice->status->isOutstanding() && $invoice->remaining() > 0,
        ]);
    }

    /**
     * Download one of the Agent's own invoices as a PDF file.
     *
     * Always a PDF attachment: the page triggering it stays where it is and nothing is printed. When
     * the server cannot render PDFs (no Node/Chrome), a 503 with a translated message is returned so
     * the page can tell the Agent (JSON for the download button, a flash message otherwise).
     *
     * @param Request $request Incoming request.
     * @param AdminInvoice $invoice Invoice (404 when it belongs to another Agent).
     * @param InvoicePdfService $documents Invoice document renderer.
     * @return Response|JsonResponse|RedirectResponse PDF download, or the failure answer.
     */
    public function download(Request $request, AdminInvoice $invoice, InvoicePdfService $documents): Response|JsonResponse|RedirectResponse
    {
        abort_unless((int) $invoice->admin_id === (int) $request->user('admin')->id, 404);

        try {
            return response($documents->pdf($invoice), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$documents->fileName($invoice).'"',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Invoice PDF rendering failed.', [
                'invoice_id' => $invoice->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $message = __('platform.billing.pdf_unavailable');

            return $request->expectsJson()
                ? response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE)
                : back()->with('error', $message);
        }
    }

    /**
     * Send the Agent to the provider's checkout page for one of its own outstanding invoices.
     *
     * @param Request $request Incoming request.
     * @param AdminInvoice $invoice Invoice (404 when it belongs to another Agent).
     * @param PaymentGateway $gateway Payment gateway.
     * @return RedirectResponse Redirect to the hosted checkout.
     */
    public function pay(Request $request, AdminInvoice $invoice, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless((int) $invoice->admin_id === (int) $request->user('admin')->id, 404);
        abort_unless($gateway->isEnabled() && $invoice->status->isOutstanding() && $invoice->remaining() > 0, 404);

        return redirect()->away($gateway->checkoutUrl($invoice));
    }
}
