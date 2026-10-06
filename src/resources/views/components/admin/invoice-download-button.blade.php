{{-- Download one platform invoice as a PDF without leaving the page (resources/js/admin/billing.js fetches the
     file and saves it; an error is shown as a toast). The href is a plain attachment link for no-JS browsers.
     Variants: `icon` (icon only, label in a tooltip; table rows) and `primary` (labelled button). --}}
@props(['invoice', 'fileName' => null, 'variant' => 'icon'])
@php
    $iconOnly = $variant !== 'primary';
    $classes = $iconOnly
        ? 'inline-grid place-items-center w-8 h-8 rounded-lg border border-outline-variant text-primary hover:bg-primary/5 hover:border-primary/40 focus-visible:outline-2 focus-visible:outline-primary transition-colors'
        : 'inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-on-primary hover:bg-primary-container';
    $i18n = ['downloading' => __('platform.billing.downloading'), 'failed' => __('platform.billing.pdf_unavailable')];
@endphp
<a href="{{ route('admin.billing.download', $invoice) }}" download="{{ $fileName ?? '' }}" data-invoice-download
    data-file-name="{{ $fileName ?? '' }}" data-i18n='@json($i18n)' class="{{ $classes }} disabled:opacity-60"
    @if ($iconOnly) data-tooltip="{{ __('platform.billing.download_pdf') }}" @else title="{{ __('platform.billing.download_pdf') }}" @endif
    aria-label="{{ __('platform.billing.download_pdf') }} {{ $invoice->number }}">
    <span class="material-symbols-outlined {{ $iconOnly ? '!text-[18px]' : 'text-[16px]' }}" data-invoice-download-icon aria-hidden="true">download</span>
    @unless ($iconOnly)
        <span>{{ __('platform.billing.download_pdf') }}</span>
    @endunless
</a>
