{{-- Ledger table of the fund page: rendered with the page and returned alone (JSON `html`) when the type filter or
     the pagination is used, so the page script swaps it in place without a reload. --}}
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
    $typeStyles = [
        'deposit' => 'bg-emerald-100 text-emerald-800',
        'expense' => 'bg-red-50 text-red-700',
        'debt_payment' => 'bg-amber-100 text-amber-800',
        'adjustment' => 'bg-surface-container text-on-surface-variant',
    ];
@endphp
@if ($entries->isEmpty())
    <div class="p-4" data-fund-empty>
        <x-admin.empty-state icon="account_balance_wallet" :title="__('room_fund.empty')" :description="($filtered ?? false) ? __('room_fund.empty_filtered') : __('room_fund.empty_description')" />
    </div>
@else
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
            <tbody class="divide-y divide-outline-variant/60" data-fund-rows>
                @foreach ($entries as $entry)
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
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="border-t border-outline-variant/60 p-3" data-fund-pagination>{{ $entries->links() }}</div>
    @endif
@endif
