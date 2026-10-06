{{-- Download one platform invoice as a PDF without leaving the page (resources/js/admin/billing.js fetches the
     file and saves it; an error is shown as a toast). The href is a plain attachment link for no-JS browsers. --}}
@props(['invoice', 'fileName' => null, 'variant' => 'compact'])
@php
    $classes = $variant === 'primary'
        ? 'inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-on-primary hover:bg-primary-container'
        : 'inline-flex items-center gap-1 rounded-md border border-outline-variant px-1.5 py-0.5 text-[11px] font-semibold text-primary hover:bg-primary/5';
    $i18n = ['downloading' => __('platform.billing.downloading'), 'failed' => __('platform.billing.pdf_unavailable')];
@endphp
<a href="{{ route('admin.billing.download', $invoice) }}" download="{{ $fileName ?? '' }}" data-invoice-download
    data-file-name="{{ $fileName ?? '' }}" data-i18n='@json($i18n)' class="{{ $classes }} disabled:opacity-60"
    title="{{ __('platform.billing.download_pdf') }}" aria-label="{{ __('platform.billing.download_pdf') }} {{ $invoice->number }}">
    <span class="material-symbols-outlined {{ $variant === 'primary' ? 'text-[16px]' : 'text-[15px]' }}" data-invoice-download-icon>download</span>
    <span>{{ $variant === 'primary' ? __('platform.billing.download_pdf') : __('platform.billing.download_pdf_short') }}</span>
</a>
