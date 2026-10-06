<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Package;
use App\Services\Billing\InvoicePdfService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * An Agent downloads its own platform invoices as PDF from /admin/billing.
 */
class InvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Admin $alice;

    private Admin $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $package = Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        foreach (['alice', 'bob'] as $name) {
            $this->{$name} = Admin::create(['name' => ucfirst($name), 'company' => 'Tea Corp', 'email' => $name.'@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
            app(SubscriptionService::class)->activate($this->{$name}, $package);
        }
        $this->artisan('billing:generate-invoices')->assertSuccessful();
    }

    private function invoiceOf(Admin $admin): AdminInvoice
    {
        return $admin->invoices()->latest('id')->firstOrFail();
    }

    public function test_billing_page_links_each_invoice_pdf(): void
    {
        $invoice = $this->invoiceOf($this->alice);

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee(route('admin.billing.download', $invoice), false);
    }

    public function test_agent_downloads_its_invoice_as_pdf(): void
    {
        $invoice = $this->invoiceOf($this->alice);
        $this->mock(InvoicePdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('pdf')->once()->andReturn('%PDF-1.4 test');
            $mock->shouldReceive('fileName')->andReturn('drinkflow-invoice-test.pdf');
        });

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.download', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="drinkflow-invoice-test.pdf"');
    }

    public function test_invoice_pdf_is_rendered_in_php_without_a_browser(): void
    {
        $invoice = $this->invoiceOf($this->alice);
        config(['laravel-pdf.driver' => 'dompdf']);

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.download', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-', false);
    }

    public function test_japanese_pdf_without_a_cjk_font_uses_english_labels_and_keeps_the_locale(): void
    {
        $invoice = $this->invoiceOf($this->alice);
        config(['laravel-pdf.driver' => 'dompdf', 'pdf.cjk_font_path' => storage_path('fonts/missing-font.ttf')]);
        app()->setLocale('ja');
        $rendered = [];
        \Illuminate\Support\Facades\View::composer('admin.billing.invoice-document', function () use (&$rendered): void {
            $rendered[] = [app()->getLocale(), func_get_arg(0)->getData()['cjkFontPath'] ?? null];
        });

        $pdf = app(InvoicePdfService::class)->pdf($invoice);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame([['en', null]], $rendered);
        $this->assertSame('ja', app()->getLocale());
    }

    public function test_failed_pdf_rendering_never_opens_a_print_page(): void
    {
        $invoice = $this->invoiceOf($this->alice);
        $this->partialMock(InvoicePdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('pdf')->andThrow(new \RuntimeException('Chrome missing'));
        });

        // The download button (fetch) gets a JSON error it shows as a toast…
        $this->actingAs($this->alice, 'admin')->getJson(route('admin.billing.download', $invoice))
            ->assertStatus(503)
            ->assertJson(['message' => __('platform.billing.pdf_unavailable')]);
        // …and a plain link goes back with the same message, never to a printable page.
        $this->actingAs($this->alice, 'admin')->from(route('admin.billing.index'))->get(route('admin.billing.download', $invoice))
            ->assertRedirect(route('admin.billing.index'))
            ->assertSessionHas('error', __('platform.billing.pdf_unavailable'));
    }

    public function test_invoice_view_page_shows_the_document_with_a_download_button(): void
    {
        $invoice = $this->invoiceOf($this->alice);

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.show', $invoice))
            ->assertOk()
            ->assertSee((string) $invoice->number)
            ->assertSee('data-invoice-document', false)
            ->assertSee('srcdoc=', false)
            ->assertSee('data-invoice-download', false)
            ->assertSee(route('admin.billing.download', $invoice), false)
            ->assertDontSee('window.print()', false);
    }

    public function test_billing_history_links_to_view_and_download_each_invoice(): void
    {
        $invoice = $this->invoiceOf($this->alice);

        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee(route('admin.billing.show', $invoice), false)
            ->assertSee('data-invoice-download', false)
            ->assertSee(route('admin.billing.download', $invoice), false);
    }

    public function test_another_agents_invoice_is_not_found(): void
    {
        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.download', $this->invoiceOf($this->bob)))->assertNotFound();
        $this->actingAs($this->alice, 'admin')->get(route('admin.billing.show', $this->invoiceOf($this->bob)))->assertNotFound();
    }
}
