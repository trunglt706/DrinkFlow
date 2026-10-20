<x-admin.layout :title="__('room_fund.title')" active="fund" :room="$room">
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $types = \App\Enums\RoomFundTransactionType::cases();
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
        $maxAmount = \App\Http\Requests\StoreRoomFundTransactionRequest::MAX_AMOUNT;
        $typeStyles = [
            'deposit' => 'bg-emerald-100 text-emerald-800',
            'expense' => 'bg-red-50 text-red-700',
            'debt_payment' => 'bg-amber-100 text-amber-800',
            'adjustment' => 'bg-surface-container text-on-surface-variant',
        ];
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('room_fund.title') }}</h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('room_fund.description') }}</p>
            </div>
            <a href="{{ route('admin.fund.export', array_filter(['room' => $room, 'type' => $type?->value])) }}" data-fund-export
                class="inline-flex items-center gap-2 self-start rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">download</span>{{ __('room_fund.export') }}
            </a>
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

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5"
            x-data="{ type: @js(old('type', 'deposit')) }">
            <h2 class="text-sm font-bold text-on-surface">{{ __('room_fund.form_title') }}</h2>
            <form method="POST" action="{{ route('admin.fund.store', $room) }}" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4" data-fund-form>
                @csrf
                <div class="space-y-1.5">
                    <label for="fund-type" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_type') }}</label>
                    <select id="fund-type" name="type" x-model="type" class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                        @foreach (\App\Enums\RoomFundTransactionType::manualValues() as $value)
                            <option value="{{ $value }}">{{ __('room_fund.types.'.$value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="fund-amount" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_amount') }}</label>
                    <input id="fund-amount" name="amount" type="number" step="1" required value="{{ old('amount') }}"
                        :min="type === 'adjustment' ? {{ -$maxAmount }} : 1" max="{{ $maxAmount }}"
                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs font-mono">
                    <p class="text-[11px] text-outline" x-show="type === 'adjustment'" x-cloak>{{ __('room_fund.adjustment_hint') }}</p>
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
                <div class="space-y-1.5" :class="type === 'deposit' ? '' : 'md:col-span-2'">
                    <label for="fund-note" class="block text-xs font-semibold text-on-surface">{{ __('room_fund.field_note') }}</label>
                    <input id="fund-note" name="note" type="text" maxlength="255" value="{{ old('note') }}" :required="type !== 'deposit'"
                        placeholder="{{ __('room_fund.note_placeholder') }}"
                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 text-xs">
                </div>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-on-primary hover:bg-primary/90">
                        <span class="material-symbols-outlined text-[18px]">add</span>{{ __('room_fund.submit') }}
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <nav class="flex flex-wrap gap-2 border-b border-outline-variant/60 p-3" aria-label="{{ __('room_fund.filter_label') }}">
                <a href="{{ route('admin.fund.index', $room) }}"
                    class="rounded-full px-3 py-1 text-xs font-semibold {{ $type === null ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('room_fund.filter_all') }}</a>
                @foreach ($types as $case)
                    <a href="{{ route('admin.fund.index', ['room' => $room, 'type' => $case->value]) }}"
                        class="rounded-full px-3 py-1 text-xs font-semibold {{ $type === $case ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">{{ __('room_fund.types.'.$case->value) }}</a>
                @endforeach
            </nav>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">{{ __('room_fund.col_time') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('room_fund.col_type') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('room_fund.col_note') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('room_fund.col_amount') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('room_fund.col_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($entries as $entry)
                            <tr data-fund-entry="{{ $entry->type->value }}">
                                <td class="px-5 py-3 whitespace-nowrap font-mono text-xs text-on-surface">
                                    {{ $entry->created_at?->copy()->timezone($timezone)->format('d/m/Y H:i') }}
                                    @if ($entry->creator)
                                        <small class="block font-sans text-[11px] text-outline">{{ $entry->creator->name }}</small>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $typeStyles[$entry->type->value] }}">{{ __('room_fund.types.'.$entry->type->value) }}</span>
                                </td>
                                <td class="px-5 py-3 text-xs text-on-surface">
                                    @if ($entry->roomUser)
                                        <span class="font-semibold">{{ $entry->roomUser->display_name }}</span>
                                    @endif
                                    @if ($entry->debt?->campaign)
                                        <span class="text-outline">· {{ $entry->debt->campaign->name }}</span>
                                    @endif
                                    @if ($entry->note)
                                        <span class="block text-on-surface-variant">{{ $entry->note }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-bold whitespace-nowrap {{ $entry->amount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $entry->amount >= 0 ? '+' : '-' }}{{ $money(abs($entry->amount)) }}
                                </td>
                                <td class="px-5 py-3 text-right font-mono whitespace-nowrap text-on-surface">{{ $money($entry->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-outline">{{ __('room_fund.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entries->hasPages())
                <div class="border-t border-outline-variant/60 p-3">{{ $entries->links() }}</div>
            @endif
        </section>
    </div>
</x-admin.layout>
