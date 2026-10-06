@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $tones = ['open' => 'bg-amber-50 text-amber-800', 'overdue' => 'bg-error-container text-on-error-container', 'paid' => 'bg-emerald-50 text-emerald-800', 'void' => 'bg-surface-container text-on-surface-variant'];
@endphp
<x-admin.layout :title="__('platform.billing.history_title')" active="billing" :breadcrumb="__('platform.billing.history_title')">
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('platform.billing.history_title') }}</h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('platform.billing.history_subtitle') }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-3" data-billing-balance="{{ $balance }}">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-outline">{{ __('platform.billing.amount_due') }}</p>
                <p class="text-xl font-bold {{ $balance > 0 ? 'text-error' : 'text-primary' }}">{{ $money($balance) }}</p>
            </div>
        </section>

        <x-admin.billing-warning />

        @if ($awaitingFirstPayment)
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status" data-billing-awaiting-payment>
                <span class="w-10 h-10 shrink-0 grid place-items-center rounded-xl bg-white text-amber-600 ring-1 ring-amber-200" aria-hidden="true">
                    <span class="material-symbols-outlined">lock_clock</span>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">{{ __('platform.billing.awaiting_first_payment_title') }}</p>
                    <p class="mt-0.5 text-xs">{{ __('platform.billing.awaiting_first_payment_body') }}</p>
                    <p class="mt-1 text-xs text-amber-800/80">{{ __('platform.billing.first_payment_locked') }}</p>
                </div>
                @if ($firstInvoice)
                    <a href="{{ route('admin.billing.show', $firstInvoice) }}"
                        class="shrink-0 inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold shadow-sm transition-colors">
                        <span class="material-symbols-outlined !text-[17px]" aria-hidden="true">credit_card</span>
                        {{ __('platform.billing.first_payment_cta', ['number' => $firstInvoice->number]) }}
                    </a>
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ session('error') }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant"><h2 class="text-sm font-bold text-on-surface">{{ __('platform.billing.invoices') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table-colgroup w-full min-w-[56rem] table-fixed text-sm">
                    <colgroup>
                        <col class="w-48">
                        <col class="w-56">
                        <col>
                        <col>
                        <col class="w-40">
                        <col class="w-36">
                        <col class="w-28">
                    </colgroup>
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">{{ __('platform.billing.invoice') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('platform.subscriptions.period') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('platform.billing.total') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('platform.billing.paid') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('platform.billing.due_at') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('validation.attributes.status') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="px-5 py-3">
                                    <strong class="font-mono whitespace-nowrap">{{ $invoice->number }}</strong>
                                    <small class="block text-outline">{{ $invoice->package?->name }}@if ($invoice->credit > 0) · {{ __('platform.billing.credit', ['amount' => $money($invoice->credit)]) }}@endif</small>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $invoice->period_start->toAppDate() }} → {{ $invoice->period_end->toAppDate() }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">{{ $money($invoice->total) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">{{ $money($invoice->paid_amount) }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $invoice->due_at->toAppDate() }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $tones[$invoice->status->value] ?? '' }}">{{ __('platform.billing.status.'.$invoice->status->value) }}</span>
                                    @if ($onlinePayment && $invoice->status->isOutstanding() && $invoice->remaining() > 0)
                                        <a href="{{ route('admin.billing.pay', $invoice) }}" class="ml-2 text-xs font-semibold text-primary hover:underline">{{ __('platform.billing.pay_online') }}</a>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.billing.show', $invoice) }}" data-invoice-view
                                            class="inline-grid place-items-center w-8 h-8 rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container hover:text-on-surface focus-visible:outline-2 focus-visible:outline-primary transition-colors"
                                            data-tooltip="{{ __('platform.billing.view_invoice') }}" aria-label="{{ __('platform.billing.view_invoice') }} {{ $invoice->number }}">
                                            <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">visibility</span>
                                        </a>
                                        <x-admin.invoice-download-button :invoice="$invoice" :file-name="$documents->fileName($invoice)" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-4">
                                <x-admin.empty-state icon="receipt_long" :title="__('platform.billing.no_invoices')" />
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())
                <div class="px-5 py-3">{{ $invoices->links() }}</div>
            @endif
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant"><h2 class="text-sm font-bold text-on-surface">{{ __('platform.billing.payments') }}</h2></div>
            <ul class="divide-y divide-outline-variant/60 text-sm">
                @forelse ($payments as $payment)
                    <li class="px-5 py-3 flex flex-wrap justify-between gap-2">
                        <span>{{ $payment->paid_at->toAppDateTime() }} · <span class="font-mono">{{ $payment->invoice?->number }}</span> · {{ __('platform.billing.methods.'.$payment->method->value) }}</span>
                        <strong>{{ $money($payment->amount) }}</strong>
                    </li>
                @empty
                    <li class="p-4">
                        <x-admin.empty-state icon="payments" :title="__('platform.billing.no_payments')" />
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin.layout>
