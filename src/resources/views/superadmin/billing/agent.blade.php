@extends('superadmin.layout', ['title' => $agent->name, 'active' => 'outstanding'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $fieldInput = 'sa-input !min-w-0 w-full';
    @endphp
    <x-superadmin.page-header :back="route('superadmin.billing.outstanding')" :back-label="__('platform.billing.outstanding_title')" :title="$agent->name">
        <x-slot:meta><p class="sa-heading-meta"><x-superadmin.sensitive :value="$agent->email" /> · {{ __('platform.billing.balance', ['amount' => $money($balance)]) }}</p></x-slot:meta>
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <section class="sa-card sa-section">
        <div class="sa-section-header"><div><h2>{{ __('platform.billing.invoices') }}</h2></div></div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-mono">{{ __('platform.billing.invoice') }}</th>
                        <th class="col-date">{{ __('platform.subscriptions.period') }}</th>
                        <th class="text-right num">{{ __('platform.billing.total') }}</th>
                        <th class="text-right num">{{ __('platform.billing.paid') }}</th>
                        <th class="col-date">{{ __('platform.billing.due_at') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        @if ($canManage)
                            <th class="col-actions">{{ __('superadmin.common.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="col-mono">
                                <strong class="font-mono">{{ $invoice->number }}</strong>
                                <small class="block text-outline">{{ $invoice->package?->name }}@if ($invoice->credit > 0) · {{ __('platform.billing.credit', ['amount' => $money($invoice->credit)]) }}@endif</small>
                            </td>
                            <td class="whitespace-nowrap col-date">{{ $invoice->period_start->toAppDate() }} → {{ $invoice->period_end->toAppDate() }}</td>
                            <td class="text-right whitespace-nowrap num">{{ $money($invoice->total) }}</td>
                            <td class="text-right whitespace-nowrap num">{{ $money($invoice->paid_amount) }}</td>
                            <td class="whitespace-nowrap col-date">{{ $invoice->due_at->toAppDate() }}</td>
                            <td class="col-status">@include('superadmin.billing._invoice-status', ['invoice' => $invoice])</td>
                            @if ($canManage)
                                <td class="col-actions">
                                    @if ($invoice->status->isOutstanding())
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" class="sa-button" data-modal-open="payment-modal-{{ $invoice->id }}"><span class="material-symbols-outlined text-[16px]">price_check</span>{{ __('platform.billing.mark_paid') }}</button>
                                            @if ($invoice->paid_amount === 0)
                                                <button type="button" class="sa-button secondary" data-modal-open="void-modal-{{ $invoice->id }}"><span class="material-symbols-outlined text-[16px]">block</span>{{ __('platform.billing.void') }}</button>
                                            @endif
                                        </div>
                                    @elseif ($invoice->void_reason)
                                        <small class="text-outline">{{ $invoice->void_reason }}</small>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 7 : 6 }}"><x-superadmin.empty-state icon="receipt_long" :title="__('platform.billing.no_invoices')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage)
        {{-- One payment / void dialog per open invoice, opened from its row (keeps the table compact). --}}
        @foreach ($invoices->filter(static fn ($invoice): bool => $invoice->status->isOutstanding()) as $invoice)
            <x-superadmin.modal id="payment-modal-{{ $invoice->id }}" icon="payments" tone="info" max-width="max-w-md"
                :title="__('platform.billing.payment_modal_title', ['number' => $invoice->number])" :description="__('superadmin.modals.confirm.record_payment')">
                <form method="POST" action="{{ route('superadmin.billing.payments.store', $invoice) }}" class="space-y-4">
                    @csrf
                    <dl class="sa-detail-list">
                        <div class="sa-detail-row"><dt>{{ __('platform.billing.total') }}</dt><dd>{{ $money($invoice->total) }}</dd></div>
                        <div class="sa-detail-row"><dt>{{ __('platform.billing.remaining') }}</dt><dd><strong>{{ $money($invoice->remaining()) }}</strong></dd></div>
                    </dl>
                    <div>
                        <label for="payment-amount-{{ $invoice->id }}" class="sa-label">{{ __('platform.billing.amount') }} <span class="text-error">*</span></label>
                        <span class="relative block">
                            <input id="payment-amount-{{ $invoice->id }}" name="amount" type="text" inputmode="numeric" required maxlength="14" autocomplete="off"
                                data-number-input data-group-separator="." value="{{ number_format($invoice->remaining(), 0, ',', '.') }}" class="{{ $fieldInput }} !pr-9 text-right tabular-nums">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[13px] font-semibold text-outline" aria-hidden="true">{{ __('global.common.money_suffix') }}</span>
                        </span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="payment-method-{{ $invoice->id }}" class="sa-label">{{ __('platform.billing.method') }}</label>
                            <select id="payment-method-{{ $invoice->id }}" name="method" class="{{ $fieldInput }}">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->value }}">{{ __('platform.billing.methods.'.$method->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment-reference-{{ $invoice->id }}" class="sa-label">{{ __('platform.billing.reference') }}</label>
                            <input id="payment-reference-{{ $invoice->id }}" name="reference" type="text" maxlength="120" class="{{ $fieldInput }}">
                        </div>
                    </div>
                    <div class="sa-modal-footer">
                        <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                        <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">price_check</span>{{ __('platform.billing.mark_paid') }}</button>
                    </div>
                </form>
            </x-superadmin.modal>
            @if ($invoice->paid_amount === 0)
                <x-superadmin.modal id="void-modal-{{ $invoice->id }}" icon="block" tone="danger" max-width="max-w-md"
                    :title="__('platform.billing.void_modal_title', ['number' => $invoice->number])" :description="__('platform.billing.void_confirm', ['number' => $invoice->number])">
                    <form method="POST" action="{{ route('superadmin.billing.invoices.void', $invoice) }}" class="space-y-4">
                        @csrf
                        <p class="sa-hint">{{ __('superadmin.modals.confirm.void_invoice') }}</p>
                        <div>
                            <label for="void-reason-{{ $invoice->id }}" class="sa-label">{{ __('platform.billing.void_reason') }} <span class="text-error">*</span></label>
                            <textarea id="void-reason-{{ $invoice->id }}" name="reason" rows="3" required minlength="3" maxlength="1000" class="{{ $fieldInput }}"></textarea>
                        </div>
                        <div class="sa-modal-footer">
                            <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                            <button type="submit" class="sa-button danger"><span class="material-symbols-outlined text-[16px]">block</span>{{ __('platform.billing.void') }}</button>
                        </div>
                    </form>
                </x-superadmin.modal>
            @endif
        @endforeach
    @endif

    <section class="sa-card sa-section mt-4">
        <div class="sa-section-header"><div><h2>{{ __('platform.billing.payments') }}</h2></div></div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead><tr><th class="col-date">{{ __('platform.billing.paid_at') }}</th><th class="col-mono">{{ __('platform.billing.invoice') }}</th><th class="text-right num">{{ __('platform.billing.amount') }}</th><th>{{ __('platform.billing.method') }}</th><th>{{ __('platform.billing.reference') }}</th><th>{{ __('platform.billing.recorded_by') }}</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap col-date">{{ $payment->paid_at->toAppDateTime() }}</td>
                            <td class="font-mono col-mono">{{ $payment->invoice?->number }}</td>
                            <td class="text-right whitespace-nowrap num">{{ $money($payment->amount) }}</td>
                            <td>{{ __('platform.billing.methods.'.$payment->method->value) }}</td>
                            <td>{{ $payment->reference ?? '—' }}</td>
                            <td>{{ $payment->recordedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-superadmin.empty-state icon="payments" :title="__('platform.billing.no_payments')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
