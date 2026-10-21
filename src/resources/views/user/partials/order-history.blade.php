{{-- "My orders" history: the member's earlier orders (the live campaign's order is shown above), filtered by code,
     dates, self / proxy and status, 10 per page. The filter is a GET form; the page keeps the filters in its URL. --}}
@php
    $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
    $filtered = $historyFilters !== [];
    $statusStyles = [
        'submitted' => 'bg-sky-50 text-sky-700 border-sky-200',
        'confirmed' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'ordering' => 'bg-amber-50 text-amber-700 border-amber-200',
        'ordered' => 'bg-amber-50 text-amber-700 border-amber-200',
        'delivering' => 'bg-violet-50 text-violet-700 border-violet-200',
        'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
    $memberName = static fn ($member): string => (string) ($member?->display_name ?: $member?->globalUser?->name ?: __('global.common.user'));
    $field = 'h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-900 focus:border-[#006948] focus:ring-1 focus:ring-[#006948] outline-none';
@endphp
<section id="order-history" class="bg-white border border-slate-200/80 rounded-xl p-4 sm:p-5 shadow-2xs" data-order-history>
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                <span class="material-symbols-outlined text-[18px] text-[#006948]" aria-hidden="true">history</span>{{ __('room.orders.history_title') }}
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('room.orders.history_desc') }}</p>
        </div>
        <span class="text-xs font-semibold text-slate-500" data-order-history-total>{{ trans_choice('room.orders.history_total', $historyOrders->total(), ['count' => $historyOrders->total()]) }}</span>
    </div>

    <form method="GET" action="{{ route('user.orders.index', $room) }}#order-history" class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-6" data-order-history-filter role="search" aria-label="{{ __('room.orders.history_title') }}">
        <label class="col-span-2 md:col-span-2">
            <span class="mb-1 block text-[11px] font-semibold text-slate-600">{{ __('room.orders.filter_code') }}</span>
            <input type="search" name="code" value="{{ $historyFilters['code'] ?? '' }}" maxlength="64" placeholder="{{ __('room.orders.filter_code_placeholder') }}" class="{{ $field }}">
        </label>
        <label>
            <span class="mb-1 block text-[11px] font-semibold text-slate-600">{{ __('room.orders.filter_from') }}</span>
            <x-date-input name="date_from" :value="$historyFilters['date_from'] ?? null" :input-class="$field" />
        </label>
        <label>
            <span class="mb-1 block text-[11px] font-semibold text-slate-600">{{ __('room.orders.filter_to') }}</span>
            <x-date-input name="date_to" :value="$historyFilters['date_to'] ?? null" :input-class="$field" />
        </label>
        <label>
            <span class="mb-1 block text-[11px] font-semibold text-slate-600">{{ __('room.orders.filter_type') }}</span>
            <select name="type" class="{{ $field }}">
                <option value="">{{ __('room.orders.filter_all') }}</option>
                @foreach (\App\Services\Order\MemberOrderPageService::HISTORY_TYPES as $historyType)
                    <option value="{{ $historyType }}" @selected(($historyFilters['type'] ?? '') === $historyType)>{{ __('room.orders.history_type_'.$historyType) }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-[11px] font-semibold text-slate-600">{{ __('room.orders.filter_status') }}</span>
            <select name="status" class="{{ $field }}">
                <option value="">{{ __('room.orders.filter_all') }}</option>
                @foreach (\App\Enums\OrderStatus::cases() as $orderStatus)
                    <option value="{{ $orderStatus->value }}" @selected(($historyFilters['status'] ?? '') === $orderStatus->value)>{{ $orderStatus->label() }}</option>
                @endforeach
            </select>
        </label>
        <div class="col-span-2 md:col-span-6 flex flex-wrap items-center justify-end gap-2">
            @if ($errors->hasAny(['code', 'date_from', 'date_to', 'type', 'status']))
                <p class="mr-auto text-xs font-semibold text-red-600" role="alert">{{ $errors->first() }}</p>
            @endif
            @if ($filtered)
                <a href="{{ route('user.orders.index', $room) }}#order-history" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">restart_alt</span>{{ __('room.orders.filter_reset') }}
                </a>
            @endif
            <button type="submit" class="inline-flex h-9 items-center gap-1 rounded-lg bg-[#006948] px-4 text-xs font-semibold text-white hover:bg-[#005137] cursor-pointer">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">search</span>{{ __('room.orders.filter_submit') }}
            </button>
        </div>
    </form>

    @if ($historyOrders->isEmpty())
        <div class="mt-4 rounded-xl border border-dashed border-slate-200 py-8 text-center" data-order-history-empty>
            <span class="material-symbols-outlined text-[28px] text-slate-300" aria-hidden="true">{{ $filtered ? 'search_off' : 'receipt_long' }}</span>
            <p class="mt-1 text-xs font-semibold text-slate-700">{{ $filtered ? __('room.orders.history_empty_filtered') : __('room.orders.history_empty') }}</p>
        </div>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-3 py-2.5 text-left">{{ __('room.orders.col_code') }}</th>
                        <th class="px-3 py-2.5 text-left">{{ __('room.orders.col_campaign') }}</th>
                        <th class="px-3 py-2.5 text-left">{{ __('room.orders.col_time') }}</th>
                        <th class="px-3 py-2.5 text-left">{{ __('room.orders.col_type') }}</th>
                        <th class="px-3 py-2.5 text-left">{{ __('room.orders.col_status') }}</th>
                        <th class="px-3 py-2.5 text-right">{{ __('room.orders.col_total') }}</th>
                        <th class="px-3 py-2.5"><span class="sr-only">{{ __('room.orders.view_order') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($historyOrders as $historyOrder)
                        @php
                            $statusValue = $historyOrder->status instanceof \BackedEnum ? $historyOrder->status->value : (string) $historyOrder->status;
                            $typeLabel = match (true) {
                                $historyOrder->placed_by_admin_id !== null => __('room.orders.history_placed_by_admin'),
                                $historyOrder->parent_id !== null && $historyOrder->room_user_id !== $roomUser->id => __('room.orders.history_proxy_for', ['name' => $memberName($historyOrder->roomUser)]),
                                $historyOrder->parent_id !== null => __('room.orders.history_placed_for_me', ['name' => $memberName($historyOrder->parent?->roomUser)]),
                                default => __('room.orders.history_type_self'),
                            };
                            $isSelf = $historyOrder->placed_by_admin_id === null && $historyOrder->parent_id === null;
                        @endphp
                        <tr data-order-history-row="{{ $historyOrder->code }}" class="hover:bg-slate-50/60">
                            <td class="px-3 py-2.5 whitespace-nowrap font-mono font-semibold text-slate-900">
                                <span class="inline-flex items-center gap-0.5">#{{ $historyOrder->code }}<x-room.copy-button :text="$historyOrder->code" align="left" :reset-ms="2000" data-order-history-copy /></span>
                            </td>
                            <td class="px-3 py-2.5 text-slate-700">
                                <span class="block max-w-[220px] truncate font-semibold">{{ $historyOrder->campaign?->name }}</span>
                                <span class="block max-w-[220px] truncate text-[11px] text-slate-500">{{ $historyOrder->campaign?->restaurant }} · {{ trans_choice('room.orders.history_items', $historyOrder->items_count, ['count' => $historyOrder->items_count]) }}</span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap font-mono text-slate-600">{{ $historyOrder->created_at?->copy()->timezone($timezone)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $isSelf ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $isSelf ? 'person' : 'group' }}</span>{{ $typeLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $statusStyles[$statusValue] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">{{ \App\Enums\OrderStatus::tryFrom($statusValue)?->label() ?? $statusValue }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap font-mono font-bold text-slate-900">{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) $historyOrder->final_amount) }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <a href="{{ route('user.orders.page', [$room, $historyOrder]) }}" class="inline-flex items-center gap-0.5 rounded-lg px-2 py-1 text-[11px] font-semibold text-[#006948] hover:bg-emerald-50 whitespace-nowrap">
                                    {{ __('room.orders.view_order') }}<span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($historyOrders->hasPages())
            <div class="mt-3 border-t border-slate-100 pt-3" data-order-history-pagination>{{ $historyOrders->links() }}</div>
        @endif
    @endif
</section>
