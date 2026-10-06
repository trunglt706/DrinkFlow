@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $tones = ['open' => 'bg-amber-50 text-amber-800', 'overdue' => 'bg-error-container text-on-error-container', 'paid' => 'bg-emerald-50 text-emerald-800', 'void' => 'bg-surface-container text-on-surface-variant'];
@endphp
<x-admin.layout :title="__('platform.billing.view_title', ['number' => $invoice->number])" active="billing" :breadcrumb="__('platform.billing.history_title')">
    <div class="space-y-5" data-invoice-page>
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.billing.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container"
                    aria-label="{{ __('platform.billing.back_to_history') }}" title="{{ __('platform.billing.back_to_history') }}">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                </a>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-on-surface flex items-center gap-2">
                        <span class="font-mono">{{ $invoice->number }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tones[$invoice->status->value] ?? '' }}">{{ __('platform.billing.status.'.$invoice->status->value) }}</span>
                    </h1>
                    <p class="mt-0.5 text-xs text-outline">{{ $invoice->package?->name }} · {{ $invoice->period_start->toAppDate() }} → {{ $invoice->period_end->toAppDate() }} · {{ $money((int) $invoice->total) }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($onlinePayment)
                    <a href="{{ route('admin.billing.pay', $invoice) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-primary px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/5">
                        <span class="material-symbols-outlined text-[16px]">credit_card</span>{{ __('platform.billing.pay_online') }}
                    </a>
                @endif
                <x-admin.invoice-download-button :invoice="$invoice" :file-name="$fileName" variant="primary" />
            </div>
        </section>

        <section class="rounded-xl border border-outline-variant bg-white overflow-hidden">
            {{-- The invoice document is self-contained HTML (same template as the PDF), isolated in a sandboxed frame. --}}
            <iframe srcdoc="{{ $document }}" sandbox title="{{ __('platform.billing.view_title', ['number' => $invoice->number]) }}"
                class="block w-full h-[1150px] border-0 bg-white" data-invoice-document></iframe>
        </section>
    </div>
</x-admin.layout>
