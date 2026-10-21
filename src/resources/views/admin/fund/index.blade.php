<x-admin.layout :title="__('room_fund.title')" active="fund" :room="$room">
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $types = \App\Enums\RoomFundTransactionType::cases();
        $maxAmount = \App\Http\Requests\StoreRoomFundTransactionRequest::MAX_AMOUNT;
        $filterClass = static fn (bool $active): string => 'rounded-full px-3 py-1 text-xs font-semibold '.($active ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high');
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('room_fund.title') }}</h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('room_fund.description') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 self-start">
                <a href="{{ route('admin.fund.export', array_filter(['room' => $room, 'type' => $type?->value])) }}" data-fund-export
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>{{ __('room_fund.export') }}
                </a>
                <button type="button" data-fund-modal-open aria-haspopup="dialog" aria-controls="fund-modal"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary hover:bg-primary/90 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>{{ __('room_fund.new_transaction') }}
                </button>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-xs font-semibold text-outline">{{ __('room_fund.balance') }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums {{ $balance > 0 ? 'text-primary' : 'text-on-surface' }}" data-fund-balance="{{ $balance }}">{{ $money($balance) }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-xs font-semibold text-outline">{{ __('room_fund.month_in') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700">+{{ $money($monthTotals['in']) }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <p class="text-xs font-semibold text-outline">{{ __('room_fund.month_out') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-red-700">-{{ $money($monthTotals['out']) }}</p>
            </div>
        </section>

        {{-- Ledger: the type filter and the pagination reload only the table (skeleton while loading). --}}
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden" data-fund-ledger-section
            data-i18n="{{ json_encode(['loadError' => __('room_fund.load_error')], JSON_UNESCAPED_UNICODE) }}">
            <nav class="flex flex-wrap gap-2 border-b border-outline-variant/60 p-3" aria-label="{{ __('room_fund.filter_label') }}">
                <a href="{{ route('admin.fund.index', $room) }}" data-fund-filter="" @if ($type === null) aria-current="true" @endif class="{{ $filterClass($type === null) }}">{{ __('room_fund.filter_all') }}</a>
                @foreach ($types as $case)
                    <a href="{{ route('admin.fund.index', ['room' => $room, 'type' => $case->value]) }}" data-fund-filter="{{ $case->value }}" @if ($type === $case) aria-current="true" @endif
                        class="{{ $filterClass($type === $case) }}">{{ __('room_fund.types.'.$case->value) }}</a>
                @endforeach
            </nav>
            <div data-fund-ledger aria-live="polite" aria-busy="false">
                @include('admin.fund.partials.ledger')
            </div>
            <template data-fund-skeleton>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <x-admin.table-skeleton :cols="5" :rows="6" />
                    </table>
                </div>
            </template>
        </section>
    </div>

    {{-- New transaction modal: checked in the browser before sending (the server checks again). --}}
    <div id="fund-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs" data-fund-modal
        role="dialog" aria-modal="true" aria-labelledby="fund-modal-title" @if ($errors->any()) data-open-on-load @endif
        data-i18n="{{ json_encode([
            'amountRequired' => __('room_fund.validation.amount_required'),
            'amountPositive' => __('room_fund.validation.amount_positive'),
            'amountNonZero' => __('room_fund.validation.amount_non_zero'),
            'amountMax' => __('room_fund.validation.amount_max', ['max' => $money($maxAmount)]),
            'noteRequired' => __('room_fund.validation.note_required'),
        ], JSON_UNESCAPED_UNICODE) }}">
        <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-surface-container-lowest shadow-2xl"
            x-data="{ type: @js(old('type', 'deposit')) }">
            <div class="flex items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                <h2 id="fund-modal-title" class="flex items-center gap-2 text-base font-bold text-on-surface">
                    <span class="material-symbols-outlined !text-[20px] text-primary" aria-hidden="true">account_balance_wallet</span>{{ __('room_fund.form_title') }}
                </h2>
                <button type="button" data-fund-modal-close aria-label="{{ __('room_fund.close') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container cursor-pointer">
                    <span class="material-symbols-outlined !text-[20px]" aria-hidden="true">close</span>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.fund.store', $room) }}" class="space-y-4 px-5 py-4" data-fund-form novalidate data-max-amount="{{ $maxAmount }}">
                @csrf
                <p class="text-[11px] text-outline"><span class="text-red-600" aria-hidden="true">*</span> {{ __('room_fund.required_hint') }}</p>
                <div class="space-y-1.5">
                    <label for="fund-type" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_type') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="fund-type" name="type" x-model="type" required data-fund-type class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                        @foreach (\App\Enums\RoomFundTransactionType::manualValues() as $value)
                            <option value="{{ $value }}">{{ __('room_fund.types.'.$value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="fund-amount" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_amount') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <div class="relative">
                        <input id="fund-amount" name="amount" type="text" inputmode="numeric" autocomplete="off" required value="{{ old('amount') }}" data-fund-amount
                            placeholder="{{ __('room_fund.amount_placeholder') }}" aria-describedby="fund-amount-error"
                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface pl-3 pr-10 text-sm font-mono">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-outline" aria-hidden="true">đ</span>
                    </div>
                    <p class="text-[11px] text-outline" x-show="type === 'adjustment'" x-cloak>{{ __('room_fund.adjustment_hint') }}</p>
                    <p id="fund-amount-error" class="hidden text-[11px] font-semibold text-red-600" data-fund-error="amount"></p>
                </div>
                <div class="space-y-1.5" x-show="type === 'deposit'">
                    <label for="fund-member" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_member') }}</label>
                    <select id="fund-member" name="room_user_id" :disabled="type !== 'deposit'" class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                        <option value="">{{ __('room_fund.member_none') }}</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected((string) old('room_user_id') === (string) $member->id)>{{ $member->display_name }} ({{ $member->user_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="fund-note" class="block text-xs font-semibold text-on-surface">
                        {{ __('room_fund.field_note') }} <span class="text-red-600" aria-hidden="true" x-show="type !== 'deposit'">*</span>
                    </label>
                    <input id="fund-note" name="note" type="text" maxlength="255" value="{{ old('note') }}" :required="type !== 'deposit'" data-fund-note
                        placeholder="{{ __('room_fund.note_placeholder') }}" aria-describedby="fund-note-error"
                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                    <p id="fund-note-error" class="hidden text-[11px] font-semibold text-red-600" data-fund-error="note"></p>
                </div>
                <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                    <button type="button" data-fund-modal-close class="h-10 rounded-xl border border-outline-variant px-4 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">{{ __('room_fund.cancel') }}</button>
                    <button type="submit" data-fund-submit class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-xs font-bold text-on-primary hover:bg-primary/90 cursor-pointer disabled:opacity-60">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>{{ __('room_fund.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
