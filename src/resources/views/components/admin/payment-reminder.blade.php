{{-- Bottom payment reminder of the admin area: an Agent using the platform that still owes platform invoices.
     Hidden on the billing pages (they already list the invoices) and while the first payment is awaited (the
     Agent is then kept on those pages). It can be collapsed to a pill; resources/js/admin/payment-reminder.js
     remembers that per invoice for the browser tab. --}}
@props(['locked' => false])
@php
    $reminderAdmin = auth('admin')->user();
    $reminder = $reminderAdmin && ! $locked && ! request()->routeIs('admin.billing.*')
        ? app(\App\Services\Billing\PaymentRequirementService::class)->reminder($reminderAdmin)
        : null;
@endphp
@if ($reminder)
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $invoice = $reminder['invoice'];
        $overdue = $reminder['overdue'];
    @endphp
    <div data-payment-reminder data-collapsed="false" data-reminder-key="{{ $invoice->id }}-{{ $reminder['count'] }}-{{ $reminder['due'] }}"
        class="group sticky bottom-0 z-20 px-3 sm:px-6 pb-3 sm:pb-4 pointer-events-none">
        <section role="region" aria-label="{{ __('platform.billing.reminder_label') }}"
            @class([
                'pointer-events-auto relative mx-auto w-full max-w-[1392px] overflow-hidden rounded-2xl border bg-white/95 backdrop-blur',
                'shadow-[0_-6px_30px_rgba(15,23,42,0.10),0_10px_30px_rgba(15,23,42,0.08)] group-data-[collapsed=true]:hidden',
                'border-error/30' => $overdue,
                'border-amber-200' => ! $overdue,
            ])>
            <span @class(['absolute inset-y-0 left-0 w-1', 'bg-error' => $overdue, 'bg-amber-400' => ! $overdue]) aria-hidden="true"></span>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 pl-4 sm:p-3.5 sm:pl-5">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <span @class([
                        'w-10 h-10 shrink-0 grid place-items-center rounded-xl',
                        'bg-error-container text-error' => $overdue,
                        'bg-amber-50 text-amber-600' => ! $overdue,
                    ]) aria-hidden="true">
                        <span class="material-symbols-outlined">{{ $overdue ? 'running_with_errors' : 'receipt_long' }}</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-on-surface sm:truncate">
                            {{ __($overdue ? 'platform.billing.reminder_overdue' : 'platform.billing.reminder_open', ['number' => $invoice->number]) }}
                        </p>
                        <p class="mt-0.5 text-xs text-on-surface-variant">
                            {{ __('platform.billing.reminder_detail', ['amount' => $money($invoice->remaining()), 'date' => $invoice->due_at->toAppDate()]) }}
                            @if ($reminder['count'] > 1)
                                <span class="text-outline">· {{ __('platform.billing.reminder_more', ['count' => $reminder['count'], 'amount' => $money($reminder['due'])]) }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:shrink-0">
                    @if ($reminder['count'] > 1)
                        <a href="{{ route('admin.billing.index') }}"
                            class="h-9 px-3 inline-flex items-center rounded-[10px] text-xs font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors">
                            {{ __('platform.billing.reminder_view_all') }}
                        </a>
                    @endif
                    <a href="{{ route('admin.billing.show', $invoice) }}" data-payment-reminder-pay
                        @class([
                            'h-9 flex-1 sm:flex-none px-4 inline-flex items-center justify-center gap-1.5 rounded-[10px] text-xs font-semibold text-white shadow-sm hover:shadow transition-all active:scale-[0.98]',
                            'bg-error hover:bg-error/90' => $overdue,
                            'bg-primary hover:bg-primary-container' => ! $overdue,
                        ])>
                        <span class="material-symbols-outlined !text-[17px]" aria-hidden="true">credit_card</span>
                        {{ __('platform.billing.reminder_pay') }}
                    </a>
                    <button type="button" data-payment-reminder-toggle
                        class="w-9 h-9 shrink-0 grid place-items-center rounded-[10px] text-outline hover:bg-surface-container hover:text-on-surface transition-colors cursor-pointer"
                        aria-label="{{ __('platform.billing.reminder_hide') }}" title="{{ __('platform.billing.reminder_hide') }}">
                        <span class="material-symbols-outlined !text-[19px]" aria-hidden="true">keyboard_arrow_down</span>
                    </button>
                </div>
            </div>
        </section>

        {{-- Collapsed form: a pill on the left, clear of the floating buttons on the right. --}}
        <button type="button" data-payment-reminder-toggle
            @class([
                'pointer-events-auto hidden group-data-[collapsed=true]:inline-flex items-center gap-2 h-10 pl-2 pr-3.5 rounded-full border bg-white text-xs font-semibold shadow-lg cursor-pointer hover:shadow-xl transition-shadow',
                'border-error/30 text-error' => $overdue,
                'border-amber-200 text-amber-800' => ! $overdue,
            ])
            aria-label="{{ __('platform.billing.reminder_show') }}" title="{{ __('platform.billing.reminder_show') }}">
            <span @class(['w-7 h-7 grid place-items-center rounded-full', 'bg-error-container' => $overdue, 'bg-amber-50' => ! $overdue]) aria-hidden="true">
                <span class="material-symbols-outlined !text-[17px]">{{ $overdue ? 'running_with_errors' : 'receipt_long' }}</span>
            </span>
            <span>{{ __('platform.billing.reminder_label') }} · {{ $money($reminder['due']) }}</span>
            <span class="material-symbols-outlined !text-[17px]" aria-hidden="true">keyboard_arrow_up</span>
        </button>
    </div>
@endif
