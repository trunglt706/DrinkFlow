@php
    $campaignStatusValue =
        $campaign->status instanceof \BackedEnum ? $campaign->status->value : (string) $campaign->status;
    $isDraft = $campaignStatusValue === 'draft';
    $isCampaignClosed = $campaignStatusValue === 'closed';
    $isCampaignLive = $campaignStatusValue === 'active';
@endphp

<x-admin.layout :title="__('admin.brand_title') . ' · ' . $campaign->name . ' · ' . __('admin.campaign_nav_orders')" active="campaigns" :room="$room">
    <div id="campaign-app" class="space-y-6" x-data="campaignOrdersComponent()" @scroll.window="hideTip()">
        <!-- Shared tooltip (fixed position so it is never clipped by scrollable tables) -->
        <div x-ref="tip" x-show="tip.show" role="tooltip"
            :style="`left: ${tip.x}px; top: ${tip.y}px;`"
            class="pointer-events-none fixed z-[70] w-max max-w-[220px] whitespace-pre-line break-words rounded-md bg-slate-900 px-2.5 py-1.5 text-left text-[10px] font-medium leading-snug text-white shadow-lg"
            style="display: none;" x-text="tip.text"></div>

        <!-- TOP SUB-NAVIGATION BAR -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center justify-between gap-4 pb-2 border-b border-outline-variant/60">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.campaigns.page', $room) }}"
                    class="p-2 rounded-xl border border-outline-variant hover:bg-surface-container text-outline hover:text-on-surface transition-colors flex items-center justify-center shrink-0"
                    title="{{ __('admin.back_to_campaigns') }}">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-bold text-on-surface tracking-tight">
                            {{ $campaign->name }}
                        </h1>
                        <x-admin.campaign-status-badge :campaign="$campaign" />
                        <span class="text-xs text-outline bg-surface-container-low border border-outline-variant px-2 py-0.5 rounded font-mono font-semibold">
                            #{{ $campaign->code ?? 'N/A' }}
                        </span>
                    </div>
                    <p class="text-xs text-outline flex items-center gap-1.5 mt-0.5">
                        <span class="material-symbols-outlined text-[15px] text-primary">storefront</span>
                        <span class="font-medium text-on-surface-variant">{{ $campaign->restaurant }}</span>
                        <span class="text-outline-variant">•</span>
                        <span>{{ $room->name }}</span>
                    </p>
                </div>
            </div>

            <!-- Sub-navigation Tabs -->
            <div class="flex items-center gap-1.5 bg-surface-container-low p-1.5 rounded-2xl border border-outline-variant/60 self-start sm:self-auto overflow-x-auto no-scrollbar max-w-full">
                <a href="{{ route('admin.campaigns.info', [$room, $campaign]) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 text-outline hover:text-on-surface hover:bg-surface-container/60">
                    <span class="material-symbols-outlined text-[18px]">info</span>
                    <span>{{ __('admin.campaign_nav_info') }}</span>
                </a>

                <a href="{{ route('admin.campaigns.orders', [$room, $campaign]) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 bg-surface-container-lowest text-primary shadow-xs border border-outline-variant/50">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                    <span>{{ __('admin.campaign_nav_orders') }}</span>
                    @if ($orders->count() > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-primary/10 text-primary">
                            {{ $orders->count() }}
                        </span>
                    @endif
                </a>

                @unless ($campaign->isLocked())
                    <a href="{{ route('admin.campaigns.menu', [$room, $campaign]) }}"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 text-outline hover:text-on-surface hover:bg-surface-container/60">
                        <span class="material-symbols-outlined text-[18px]">restaurant_menu</span>
                        <span>{{ __('admin.campaign_nav_menu') }}</span>
                    </a>
                @endunless

            </div>
        </div>

        <!-- QUICK SUMMARY STRIP -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 sm:p-5 shadow-xs">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-outline font-medium block">{{ __('admin.aggregated_items_list') }}</span>
                        <span class="text-base font-bold font-mono text-on-surface">{{ count($aggregatedItems ?? []) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">list_alt</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-outline font-medium block">{{ __('admin.orders_list_tab') }}</span>
                        <span class="text-base font-bold font-mono text-on-surface">{{ $orders->count() }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">payments</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-outline font-medium block">{{ __('admin.original_subtotal') }}</span>
                        <span class="text-base font-bold font-mono text-emerald-700">{{ \App\Support\Helpers\FormatHelper::formatCurrency($grossSubtotal ?? 0) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-outline font-medium block">{{ __('admin.remaining_debt') }}</span>
                        <span class="text-base font-bold font-mono text-amber-700">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalDebt ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABBED INTERFACE FOR ORDER AGGREGATION, INDIVIDUAL ORDERS, DEPARTMENTS & SETTLEMENT LEDGER -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden">
            <!-- Tabs Navigation Header -->
            <div class="border-b border-outline-variant/60 bg-surface-container-low px-4 pt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar max-w-full">
                    @php
                        $aggregatedCount = count($aggregatedItems ?? []);
                        $ordersCount = $orders->count();
                        $departmentsCount = count($departmentGroups ?? []);
                        $debtsCount = $campaign->debts->count();
                        $declinedCount = (int) ($declinedUsersCount ?? count($declinedUsers ?? []));
                        $unresponsiveCount = (int) ($pendingUsersCount ?? count($unresponsiveUsers ?? []));
                    @endphp

                    <!-- TAB 1: MÓN GỘP -->
                    <button type="button" @click="activeTab = 'aggregated'"
                        :class="activeTab === 'aggregated' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                        <span>{{ __('admin.aggregated_items_list') }}</span>
                        @if ($aggregatedCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-primary text-white">
                                {{ $aggregatedCount }}
                            </span>
                        @endif
                    </button>

                    <!-- TAB 2: DANH SÁCH ĐƠN HÀNG -->
                    <button type="button" @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">list_alt</span>
                        <span>{{ __('admin.orders_list_tab') }}</span>
                        @if ($ordersCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-primary text-white">
                                {{ $ordersCount }}
                            </span>
                        @endif
                    </button>

                    <!-- TAB 3: MÓN THEO PHÒNG BAN -->
                    <button type="button" @click="activeTab = 'departments'"
                        :class="activeTab === 'departments' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">corporate_fare</span>
                        <span>{{ __('admin.department_items_tab') }}</span>
                        @if ($departmentsCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-slate-600 text-white">
                                {{ $departmentsCount }}
                            </span>
                        @endif
                    </button>

                    <!-- TAB 4: SỔ NỢ CÁ NHÂN -->
                    <button type="button" @click="activeTab = 'ledger'"
                        :class="activeTab === 'ledger' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                        <span>{{ __('admin.participant_settlement_ledger') }}</span>
                        @if ($debtsCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-amber-600 text-white">
                                {{ $debtsCount }}
                            </span>
                        @endif
                    </button>

                    <!-- TAB 5: DANH SÁCH USER KHÔNG THAM GIA -->
                    <button type="button" @click="activeTab = 'declined'"
                        :class="activeTab === 'declined' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">block</span>
                        <span>{{ __('admin.declined_users_tab') }}</span>
                        @if ($declinedCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-slate-400 text-white">
                                {{ $declinedCount }}
                            </span>
                        @endif
                    </button>

                    <!-- TAB 6: DANH SÁCH USER KHÔNG PHẢN HỒI -->
                    <button type="button" @click="activeTab = 'unresponsive'"
                        :class="activeTab === 'unresponsive' ?
                            'border-primary text-primary font-bold bg-surface-container-lowest shadow-2xs' :
                            'border-transparent text-outline hover:text-on-surface hover:bg-surface-container/50'"
                        class="flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                        <span class="material-symbols-outlined text-[18px]">hourglass_empty</span>
                        <span>{{ __('admin.unresponsive_users_tab') }}</span>
                        @if ($unresponsiveCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-amber-500 text-white">
                                {{ $unresponsiveCount }}
                            </span>
                        @endif
                    </button>
                </div>
            </div>

            <!-- TAB 1: AGGREGATED ORDER LIST -->
            <div x-show="activeTab === 'aggregated'" class="p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">
                                {{ __('admin.aggregated_items_for_store') }} {{ $campaign->restaurant }}
                            </h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-primary border border-emerald-200 font-mono text-xs font-semibold">
                                {{ count($aggregatedItems ?? []) }} {{ __('admin.item_groups') }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.aggregated_items_desc') }}</p>
                    </div>
                    @if ($aggregatedCount > 0)
                        <a download data-download-button
                            href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'aggregated']) }}"
                            @click="downloadExport($event, 'aggregated')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                            <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                            <span data-download-label>{{ __('admin.download') }}</span>
                        </a>
                    @endif
                </div>

                <div class="overflow-x-auto w-full rounded-xl border border-outline-variant/60">
                    {{-- table-fixed ignores min-width on cells: the table's min width = fixed columns + 200px for the item column. --}}
                    <table class="table-colgroup w-full min-w-[680px] text-left border-collapse text-xs table-fixed">
                        <thead>
                            <tr class="h-10 bg-surface-container-low border-b border-outline-variant text-outline uppercase font-mono tracking-wider">
                                <th class="px-4 py-2 w-16 text-left">{{ __('admin.order_no') }}</th>
                                <th class="px-4 py-2 text-left min-w-[200px]">{{ __('admin.item_name_customization') }}</th>
                                <th class="px-4 py-2 w-32 text-center">{{ __('admin.quantity') }}</th>
                                <th class="px-4 py-2 w-36 text-right">{{ __('admin.unit_price') }}</th>
                                <th class="px-4 py-2 w-36 text-right">{{ __('admin.total_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($aggregatedItems as $index => $item)
                                @php
                                    $memberTooltip = $item['member_quantities']
                                        ->map(fn (array $member): string => $member['name'] . ' x ' . $member['quantity'])
                                        ->join("\n");
                                @endphp
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-4 py-3 w-16 text-left font-mono text-outline">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-left">
                                        <div class="font-semibold text-on-surface">{{ $item['name'] }}
                                            {{ $item['size'] ? '(' . $item['size'] . ')' : '' }}
                                        </div>
                                        @include('admin.partials.item-customizations', [
                                            'toppings' => $item['toppings'] ?? collect(),
                                            'icePercent' => $item['ice_percent'] ?? null,
                                            'sugarPercent' => $item['sugar_percent'] ?? null,
                                        ])
                                        @if (!empty($item['notes']) && $item['notes']->isNotEmpty())
                                            <div class="text-[11px] text-outline mt-0.5">
                                                {{ __('admin.notes') }}: {{ $item['notes']->unique()->join(' • ') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 w-32 text-center">
                                        <span tabindex="0" data-tip="{{ $memberTooltip }}"
                                            @mouseenter="showTip($event)" @mouseleave="hideTip()" @focus="showTip($event)" @blur="hideTip()"
                                            class="inline-block px-2.5 py-0.5 bg-emerald-50 border border-emerald-200 text-primary font-mono font-bold rounded cursor-help">
                                            {{ $item['quantity'] }} {{ __('admin.portions') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 w-36 text-right font-mono text-outline">
                                        {{ \App\Support\Helpers\FormatHelper::formatCurrency($item['unit_price']) }}
                                    </td>
                                    <td class="px-4 py-3 w-36 text-right font-mono font-bold text-on-surface">
                                        {{ \App\Support\Helpers\FormatHelper::formatCurrency($item['total_amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-outline">
                                        <div class="flex flex-col items-center justify-center gap-2 py-2">
                                            <span class="material-symbols-outlined text-4xl text-outline-variant">receipt_long</span>
                                            <p class="font-medium text-xs text-outline">{{ __('admin.no_items_ordered') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: INDIVIDUAL ORDERS LIST -->
            <div x-show="activeTab === 'orders'" class="p-5 space-y-4" style="display: none;">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.orders_list_tab') }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-primary border border-emerald-200 font-mono text-xs font-semibold">
                                {{ __('admin.orders_count_badge', ['count' => $orders->count()]) }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.orders_list_desc') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                    @if (($onBehalfData ?? null) && $onBehalfData['can_place'])
                        <button type="button" data-on-behalf-open
                            class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-on-primary shadow-xs hover:opacity-90 transition-opacity cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">person_add</span>
                            <span>{{ __('admin.on_behalf_button') }}</span>
                        </button>
                    @endif
                    @if ($ordersCount > 0)
                        <div class="flex items-center gap-2">
                            @if ($campaign->debts->contains(fn ($debt) => in_array($debt->status, [\App\Enums\DebtStatus::Unpaid, \App\Enums\DebtStatus::Partial, \App\Enums\DebtStatus::Pending], true) && $debt->remaining_amount > 0))
                                <button type="button" @click="showBulkConfirmModal = true" :disabled="isConfirmingAllOrders"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-primary bg-primary px-3.5 py-2 text-xs font-semibold text-on-primary hover:opacity-90 transition-colors disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="material-symbols-outlined text-[16px]">payments</span>
                                    <span>{{ __('admin.confirm_paid_orders') }}</span>
                                </button>
                            @endif
                            <a download data-download-button
                                href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'orders']) }}"
                                @click="downloadExport($event, 'orders')"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                                <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                                <span data-download-label>{{ __('admin.download') }}</span>
                            </a>
                        </div>
                    @endif
                    </div>
                </div>

                <div class="overflow-x-auto w-full rounded-xl border border-outline-variant/60">
                    {{-- table-fixed ignores min-width on cells: the table's min width = fixed columns + 200px for the item column. --}}
                    <table class="table-colgroup w-full min-w-[832px] text-left border-collapse text-xs table-fixed">
                        <thead>
                            <tr class="h-10 bg-surface-container-low border-b border-outline-variant text-outline uppercase font-mono tracking-wider">
                                <th class="px-4 py-2 w-14 text-left">{{ __('admin.order_no') }}</th>
                                <th class="px-4 py-2 w-48 text-left">{{ __('admin.member') }}</th>
                                <th class="px-4 py-2 text-left min-w-[200px]">{{ __('admin.order_items_detail') }}</th>
                                <th class="px-4 py-2 w-32 text-right">{{ __('admin.payable') }}</th>
                                <th class="px-4 py-2 w-36 text-center">{{ __('admin.order_confirmed_switch') }}</th>
                                <th class="px-4 py-2 w-28 text-center">{{ __('admin.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @php
                                $campaignSponsorPercentages = $campaign->sponsorPercentages();
                            @endphp
                            @forelse($orders as $index => $order)
                                @php
                                    $roomUser = $order->roomUser;
                                    $globalUser = $roomUser?->globalUser;
                                    $location = $globalUser?->desk_location;
                                    $sponsorPercentage = $campaignSponsorPercentages[(int) $order->room_user_id] ?? null;
                                @endphp
                                <tr class="hover:bg-surface-container-low/50 transition-colors align-top">
                                    <td class="px-4 py-3 w-14 text-left font-mono text-outline">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 w-48 text-left">
                                        <div>
                                            <div class="font-semibold text-on-surface">
                                                {{ $roomUser?->display_name ?? __('admin.member') }}
                                                @if ($sponsorPercentage !== null)
                                                    <span tabindex="0" data-order-sponsor-mark
                                                        data-tip="{{ __('admin.order_sponsor_tooltip', ['percentage' => $sponsorPercentage]) }}"
                                                        aria-label="{{ __('admin.order_sponsor_tooltip', ['percentage' => $sponsorPercentage]) }}"
                                                        @mouseenter="showTip($event)" @mouseleave="hideTip()" @focus="showTip($event)" @blur="hideTip()"
                                                        class="material-symbols-outlined ml-1 align-middle text-[15px] text-amber-600 cursor-help" style="font-variation-settings:'FILL' 1">volunteer_activism</span>
                                                @endif
                                                @if ($order->parent?->roomUser)
                                                    <span tabindex="0"
                                                        data-tip="{{ __('admin.proxy_order_info', ['name' => $order->parent->roomUser?->display_name ?? __('admin.member'), 'email' => $order->parent->roomUser?->globalUser?->email ?? '']) }}"
                                                        @mouseenter="showTip($event)" @mouseleave="hideTip()" @focus="showTip($event)" @blur="hideTip()"
                                                        class="material-symbols-outlined ml-1 align-middle text-[15px] text-primary cursor-help">info</span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] font-mono text-outline flex items-center gap-1.5 flex-wrap mt-0.5">
                                                <span class="inline-flex items-center gap-1">
                                                    <span>{{ $order->code }}</span>
                                                    <button type="button" @click="copyOrderCode(@js($order->code), $event)"
                                                        data-tip="{{ __('admin.copy_order_code') }}"
                                                        @mouseenter="showTip($event)" @mouseleave="hideTip()" @focus="showTip($event)" @blur="hideTip()"
                                                        class="text-outline hover:text-primary transition-colors cursor-pointer"
                                                        aria-label="{{ __('admin.copy_order_code') }}">
                                                        <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                                    </button>
                                                </span>
                                                @if ($location)
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-surface-container text-on-surface-variant text-[9px] font-medium border border-outline-variant/60">
                                                        <span class="material-symbols-outlined text-[11px]">location_on</span>
                                                        {{ $location }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-left">
                                        <div class="space-y-1.5">
                                            @foreach ($order->items as $item)
                                                <div class="text-xs">
                                                    <span class="font-semibold text-on-surface">{{ $item->item_name }}</span>
                                                    @if ($item->size_name)
                                                        <span class="text-outline">({{ $item->size_name }})</span>
                                                    @endif
                                                    <span class="text-primary font-mono font-bold">x{{ $item->quantity }}</span>
                                                    <span class="text-outline font-mono text-[11px]">({{ \App\Support\Helpers\FormatHelper::formatCurrency($item->unit_price) }})</span>
                                                    @include('admin.partials.item-customizations', [
                                                        'toppings' => $item->toppings->map(fn ($topping): string => ($topping->quantity > 1 ? $topping->quantity . 'x ' : '') . $topping->topping_name . ($topping->unit_price > 0 ? ' (+' . \App\Support\Helpers\FormatHelper::formatCurrency((int) $topping->unit_price) . ')' : '')),
                                                        'icePercent' => $item->ice_percent,
                                                        'sugarPercent' => $item->sugar_percent,
                                                    ])
                                                    @if (!empty($item->note))
                                                        <div class="text-[11px] text-outline italic pl-2 border-l-2 border-outline-variant/60 mt-0.5">
                                                            📝 {{ $item->note }}
                                                        </div>
                                                    @endif
                                                    @if ($item->is_self_paid)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 mt-0.5">
                                                            <span class="material-symbols-outlined text-[11px]">payments</span>{{ __('admin.self_paid_badge') }}
                                                        </span>
                                                    @endif
                                                    @if ($order->parent_id !== null)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-violet-50 text-violet-700 border border-violet-200 mt-0.5">
                                                            <span class="material-symbols-outlined text-[11px]">account_tree</span>{{ __('admin.proxy_order_badge') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if (!empty($order->note))
                                                <div class="text-[11px] text-amber-700 bg-amber-50 p-1.5 rounded border border-amber-200 mt-1">
                                                    <span class="font-semibold">{{ __('admin.order_note') }}:</span>
                                                    {{ $order->note }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 w-32 text-right font-mono font-bold text-on-surface">
                                        {{ \App\Support\Helpers\FormatHelper::formatCurrency($order->final_amount) }}
                                    </td>
                                    <td class="px-4 py-3 w-36 text-center">
                                        <div class="flex items-center justify-center min-h-[24px]">
                                            <div x-show="isUpdatingOrder['{{ $order->id }}']" class="flex items-center justify-center gap-1 text-primary text-[11px] font-mono">
                                                <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                                            </div>
                                            <label x-show="!isUpdatingOrder['{{ $order->id }}']" class="relative inline-flex items-center cursor-pointer select-none">
                                                <input type="checkbox"
                                                    :checked="orderStatus['{{ $order->id }}'] === 'confirmed'"
                                                    :disabled="isUpdatingOrder['{{ $order->id }}'] || !['submitted', 'confirmed'].includes(orderStatus['{{ $order->id }}'])"
                                                    @change="toggleOrderConfirmation({{ $order->id }})"
                                                    class="sr-only peer">
                                                <div class="w-8 h-4 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-primary peer-disabled:opacity-40 peer-disabled:cursor-not-allowed"></div>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 w-28 text-center">
                                        <details class="relative inline-block text-left">
                                            <summary class="list-none mx-auto cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg text-secondary hover:text-primary hover:bg-surface-container-high border border-outline-variant/60 transition-colors" aria-label="{{ __('admin.th_actions') }}">
                                                <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                            </summary>
                                            <div class="absolute right-0 mt-1 w-52 z-20 rounded-xl border border-outline-variant bg-surface-container-lowest shadow-xl p-1.5 space-y-0.5 text-left">
                                                <button type="button" @click="openOrderDetail({{ $order->id }}); $el.closest('details').open = false"
                                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left cursor-pointer">
                                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                    <span>{{ __('admin.view_order_detail') }}</span>
                                                </button>
                                                @if (($onBehalfData ?? null) && $order->status->isActive() && ! in_array($order->payment_status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::Pending], true))
                                                    {{-- Same rule as the orders page: an order already paid or awaiting payment review is not edited. --}}
                                                    <button type="button" data-order-edit="{{ $order->id }}" data-order-code="{{ $order->code }}" data-order-member="{{ $roomUser?->display_name ?? __('admin.member') }}"
                                                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left cursor-pointer">
                                                        <span class="material-symbols-outlined text-[16px] text-primary">edit_note</span>
                                                        <span>{{ __('admin.edit_order_btn') }}</span>
                                                    </button>
                                                @endif
                                                @if ($isCampaignLive)
                                                    @if ($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                                                        <button type="button" @click="openConfirmPaymentModal({{ $order->id }}); $el.closest('details').open = false"
                                                            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-emerald-700 hover:bg-emerald-50 text-left cursor-pointer">
                                                            <span class="material-symbols-outlined text-[16px]">paid</span>
                                                            <span>{{ __('admin.confirm_order_payment_btn') }}</span>
                                                        </button>
                                                    @endif
                                                    <button type="button" @click="openDeleteOrderModal({{ $order->id }}); $el.closest('details').open = false"
                                                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-error hover:bg-error-container/30 text-left cursor-pointer">
                                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                                        <span>{{ __('admin.delete_order_btn') }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-outline">
                                        <div class="flex flex-col items-center justify-center gap-2 py-2">
                                            <span class="material-symbols-outlined text-4xl text-outline-variant">shopping_cart</span>
                                            <p class="font-medium text-xs text-outline">{{ __('admin.no_orders_in_campaign') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: ITEMS GROUPED BY DEPARTMENT -->
            <div x-show="activeTab === 'departments'" class="p-5 space-y-4" style="display: none;">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.department_items_tab') }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-primary border border-emerald-200 font-mono text-xs font-semibold">
                                {{ __('admin.department_count_badge', ['count' => count($departmentGroups ?? [])]) }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.department_summary') }}</p>
                    </div>
                    @if ($departmentsCount > 0)
                      <div class="flex flex-wrap items-center gap-2">
                        <label for="department-filter" class="flex items-center gap-1.5 text-xs font-semibold text-outline">
                            <span class="material-symbols-outlined text-[16px]">location_on</span>
                            {{ __('admin.desk_location') }}
                        </label>
                        <select id="department-filter" x-model="departmentFilter"
                            class="h-9 max-w-[220px] rounded-xl border border-outline-variant bg-surface px-3 text-xs font-semibold text-on-surface outline-hidden focus:border-primary cursor-pointer">
                            <option value="all">{{ __('admin.filter_all') }}</option>
                            @foreach ($departmentGroups as $filterIndex => $filterGroup)
                                <option value="{{ $filterIndex }}">{{ $filterGroup['department'] }}</option>
                            @endforeach
                        </select>
                        <a download data-download-button
                            href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'departments']) }}"
                            @click="downloadExport($event, 'departments')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                            <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                            <span data-download-label>{{ __('admin.download') }}</span>
                        </a>
                      </div>
                    @endif
                </div>

                <div class="space-y-4">
                    @forelse($departmentGroups as $deptIndex => $dept)
                        <div x-show="departmentFilter === 'all' || departmentFilter === '{{ $deptIndex }}'"
                            class="border border-outline-variant rounded-xl overflow-hidden bg-surface-container-lowest shadow-xs">
                            <div class="p-3.5 bg-surface-container-low border-b border-outline-variant/60 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[20px]">corporate_fare</span>
                                    <h4 class="text-xs font-bold text-on-surface">{{ $dept['department'] }}</h4>
                                </div>
                                <div class="flex items-center gap-2 font-mono text-[11px]">
                                    <span class="px-2.5 py-0.5 rounded bg-surface-container text-on-surface-variant border border-outline-variant">
                                        {{ __('admin.department_members_count', ['count' => $dept['members']->count()]) }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded bg-emerald-50 text-primary border border-emerald-200 font-semibold">
                                        {{ __('admin.department_orders_count', ['count' => $dept['total_quantity']]) }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded bg-surface-container-high text-on-surface font-bold">
                                        {{ \App\Support\Helpers\FormatHelper::formatCurrency($dept['total_amount']) }}
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                {{-- table-fixed ignores min-width on cells: the table's min width = fixed columns + 200px for the item column. --}}
                                <table class="table-colgroup w-full min-w-[688px] text-left border-collapse text-xs table-fixed">
                                    <thead>
                                        <tr class="h-8 bg-surface-container-lowest border-b border-outline-variant/60 text-outline uppercase font-mono tracking-wider text-[11px]">
                                            <th class="px-4 py-2 w-14 text-left">{{ __('admin.order_no') }}</th>
                                            <th class="px-4 py-2 text-left min-w-[200px]">{{ __('admin.item_name_customization') }}</th>
                                            <th class="px-4 py-2 w-48 text-left">{{ __('admin.ordered_by') }}</th>
                                            <th class="px-4 py-2 w-28 text-center">{{ __('admin.quantity') }}</th>
                                            <th class="px-4 py-2 w-32 text-right">{{ __('admin.total_amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant/40">
                                        @foreach ($dept['items'] as $index => $item)
                                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                                <td class="px-4 py-2.5 w-14 text-left font-mono text-outline">{{ $loop->iteration }}</td>
                                                <td class="px-4 py-2.5 text-left">
                                                    <div class="font-semibold text-on-surface">
                                                        {{ $item['name'] }} {{ $item['size'] ? '(' . $item['size'] . ')' : '' }}
                                                    </div>
                                                    @include('admin.partials.item-customizations', [
                                                        'toppings' => $item['toppings'] ?? collect(),
                                                        'icePercent' => $item['ice_percent'] ?? null,
                                                        'sugarPercent' => $item['sugar_percent'] ?? null,
                                                    ])
                                                    @if (!empty($item['notes']) && $item['notes']->isNotEmpty())
                                                        <div class="text-[11px] text-outline mt-0.5">
                                                            {{ __('admin.notes') }}: {{ $item['notes']->unique()->join(' • ') }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 w-48 text-left text-on-surface-variant">
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($item['members'] as $memberEntry)
                                                            <span class="inline-block px-2 py-0.5 bg-surface-container-low border border-outline-variant/60 rounded text-[11px] text-on-surface">
                                                                {{ $memberEntry }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5 w-28 text-center">
                                                    <span class="inline-block px-2.5 py-0.5 bg-emerald-50 border border-emerald-200 text-primary font-mono font-bold rounded">
                                                        {{ $item['quantity'] }} {{ __('admin.portions') }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 w-32 text-right font-mono font-bold text-on-surface">
                                                    {{ \App\Support\Helpers\FormatHelper::formatCurrency($item['total_amount']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-outline border border-outline-variant rounded-xl bg-surface-container-lowest flex flex-col items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-4xl text-outline-variant">corporate_fare</span>
                            <p class="font-medium text-xs text-outline">{{ __('admin.no_orders_in_campaign') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- TAB 4: PARTICIPANT SETTLEMENT LEDGER -->
            <div x-show="activeTab === 'ledger'" class="p-5 space-y-4" style="display: none;">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.participant_settlement_ledger') }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-primary border border-emerald-200 font-mono text-xs font-semibold">
                                {{ __('admin.orders_count_badge', ['count' => $orders->count()]) }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.order_allocation_debt_desc') }}</p>
                    </div>
                    @if ($debtsCount > 0)
                        <a download data-download-button
                            href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'debts']) }}"
                            @click="downloadExport($event, 'debts')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                            <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                            <span data-download-label>{{ __('admin.download') }}</span>
                        </a>
                    @endif
                </div>

                @if ($isCampaignClosed || $campaign->debts->isNotEmpty())
                    <div class="overflow-x-auto w-full rounded-xl border border-outline-variant/60">
                        <table class="table-colgroup w-full min-w-[814px] text-left border-collapse text-xs table-fixed">
                            <thead>
                                <tr class="h-10 bg-surface-container-low border-b border-outline-variant text-outline uppercase font-mono tracking-wider">
                                    <th class="px-4 py-2 w-14 text-left">{{ __('admin.order_no') }}</th>
                                    <th class="px-4 py-2 w-48 text-left">{{ __('admin.member') }}</th>
                                    <th class="px-4 py-2 w-32 text-right">{{ __('admin.amount') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('admin.request_content') }}</th>
                                    <th class="px-4 py-2 w-44 text-center">{{ __('admin.status') }}</th>
                                    <th class="px-4 py-2 w-28 text-center">{{ __('admin.action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($campaign->debts as $index => $debt)
                                    @php
                                        $roomUser = $debt->roomUser;
                                        $globalUser = $roomUser?->globalUser;
                                        $location = $globalUser?->desk_location;
                                        $transferContent = $debt->note ?: $debt->code;
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/50 transition-colors align-top">
                                        <td class="px-4 py-3 w-14 text-left font-mono text-outline">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-3 w-48 text-left">
                                            <div>
                                                <div class="font-semibold text-on-surface">
                                                    {{ $roomUser?->display_name ?? __('admin.member') }}
                                                </div>
                                                <div class="text-[10px] font-mono text-outline flex items-center gap-1.5 flex-wrap mt-0.5">
                                                    @if ($globalUser?->email)
                                                        <span class="block max-w-full truncate" data-tip="{{ $globalUser->email }}"
                                                            @mouseenter="showTip($event)" @mouseleave="hideTip()">{{ $globalUser->email }}</span>
                                                    @endif
                                                    @if ($location)
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-surface-container text-on-surface-variant text-[9px] font-medium border border-outline-variant/60">
                                                            <span class="material-symbols-outlined text-[11px]">location_on</span>
                                                            {{ $location }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 w-32 text-right">
                                            <div class="font-bold font-mono text-on-surface text-xs">
                                                {{ \App\Support\Helpers\FormatHelper::formatCurrency($debt->original_amount) }}
                                            </div>
                                            <div class="text-[10px] font-mono mt-0.5"
                                                :class="debtsStatus['{{ $debt->id }}'] === 'paid' ? 'text-emerald-700' : 'text-amber-700'"
                                                x-text="debtsStatus['{{ $debt->id }}'] === 'paid' ? '{{ __('admin.filter_debt_paid') }}' : ('{{ __('admin.remaining_debt') }}: ' + formatCurrency({{ (int) $debt->remaining_amount }}))">
                                                {{ $debt->status?->value === 'paid' ? __('admin.filter_debt_paid') : __('admin.remaining_debt') . ': ' . \App\Support\Helpers\FormatHelper::formatCurrency($debt->remaining_amount) }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-left">
                                            <span class="font-mono text-xs text-primary font-semibold block">{{ $transferContent }}</span>
                                            <span class="text-[10px] font-mono font-code text-outline">#{{ $debt->code }}</span>
                                        </td>
                                        <td class="px-4 py-3 w-44 text-center">
                                            <div class="flex items-center justify-center">
                                                <select :value="debtsStatus['{{ $debt->id }}']"
                                                    :disabled="isUpdatingDebt['{{ $debt->id }}']"
                                                    @change="updateDebtStatus({{ $debt->id }}, $event.target.value)"
                                                    class="h-8 px-2 py-1 text-xs font-semibold rounded-lg border transition-colors outline-hidden cursor-pointer"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-800 border-emerald-300 focus:border-emerald-500': debtsStatus['{{ $debt->id }}'] === 'paid',
                                                        'bg-amber-50 text-amber-900 border-amber-300 focus:border-amber-500 font-bold': debtsStatus['{{ $debt->id }}'] === 'unpaid' || debtsStatus['{{ $debt->id }}'] === 'pending',
                                                        'bg-blue-50 text-blue-800 border-blue-300 focus:border-blue-500': debtsStatus['{{ $debt->id }}'] === 'partial',
                                                        'bg-surface-container text-outline border-outline-variant': debtsStatus['{{ $debt->id }}'] === 'waived'
                                                    }">
                                                    <option value="unpaid">{{ __('admin.filter_debt_unpaid') }}</option>
                                                    <option value="paid">{{ __('admin.filter_debt_paid') }}</option>
                                                    <option value="waived">{{ __('admin.status_waived') }}</option>
                                                </select>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 w-28 text-center">
                                            <button type="button" @click="openDebtDetail({{ $debt->id }})"
                                                data-tip="{{ __('admin.view_order_detail') }}"
                                                @mouseenter="showTip($event)" @mouseleave="hideTip()" @focus="showTip($event)" @blur="hideTip()"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-primary border border-outline-variant/60 transition-colors cursor-pointer"
                                                aria-label="{{ __('admin.view_order_detail') }}">
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-outline">
                                            <div class="flex flex-col items-center justify-center gap-2 py-2">
                                                <span class="material-symbols-outlined text-4xl text-outline-variant">account_balance_wallet</span>
                                                <p class="font-medium text-xs text-outline">{{ __('admin.no_debts_recorded') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 bg-amber-50/70 border border-amber-200 rounded-xl text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center mx-auto">
                            <span class="material-symbols-outlined text-[26px]">lock_clock</span>
                        </div>
                        <div class="max-w-lg mx-auto space-y-1">
                            <h4 class="text-sm font-bold text-amber-950">{{ __('admin.debt_ledger_not_closed_title') }}</h4>
                            <p class="text-xs text-amber-800 leading-relaxed">{{ __('admin.debt_ledger_closed_only_notice') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- TAB 5: DECLINED USERS LIST -->
            <div x-show="activeTab === 'declined'" class="p-5 space-y-4" style="display: none;">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.declined_users_tab') }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-mono text-xs font-semibold">
                                {{ $declinedUsersCount ?? count($declinedUsers ?? []) }} {{ __('admin.member') }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.declined_users_desc') }}</p>
                    </div>
                    @if ($declinedCount > 0)
                        <a download data-download-button
                            href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'declined']) }}"
                            @click="downloadExport($event, 'declined')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                            <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                            <span data-download-label>{{ __('admin.download') }}</span>
                        </a>
                    @endif
                </div>

                <div class="overflow-x-auto w-full rounded-xl border border-outline-variant/60">
                    <table class="table-colgroup w-full min-w-[40rem] text-left border-collapse text-xs table-fixed">
                        <thead>
                            <tr class="h-10 bg-surface-container-low border-b border-outline-variant text-outline uppercase font-mono tracking-wider">
                                <th class="px-4 py-2 w-16 text-left">{{ __('admin.order_no') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('admin.member_full_name') }}</th>
                                <th class="px-4 py-2 w-64 text-left">{{ __('admin.member_department') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($declinedUsers as $index => $u)
                                @php
                                    $globalUser = $u->globalUser;
                                    $dept = trim((string) ($globalUser?->desk_location ?? '')) ?: __('admin.unassigned_department');
                                    $name = $globalUser?->name ?? $u->display_name;
                                @endphp
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-4 py-3 w-16 text-left font-mono text-outline">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-left">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-rose-50 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0 border border-rose-200">
                                                {{ mb_substr($name, 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-on-surface">{{ $name }}</div>
                                                <div class="text-[10px] font-mono text-outline flex items-center gap-1.5 mt-0.5">
                                                    <span>{{ $globalUser->email }}</span>
                                                    @if ($globalUser->email)
                                                        <button type="button" data-copy="{{ $globalUser->email }}"
                                                            data-copied-message="{{ __('admin.copied') }}"
                                                            data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                                            data-tooltip="{{ __('admin.copy_email') }}"
                                                            aria-label="{{ __('admin.copy_email') }}"
                                                            class="inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                                            <span class="material-symbols-outlined text-[12px]">content_copy</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 w-64 text-left">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-surface-container text-on-surface-variant font-medium text-xs border border-outline-variant/60">
                                            <span class="material-symbols-outlined text-[14px] text-outline">corporate_fare</span>
                                            <span>{{ $dept }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-12 text-center text-outline">
                                        <div class="flex flex-col items-center justify-center gap-2 py-2">
                                            <span class="material-symbols-outlined text-4xl text-outline-variant">check_circle</span>
                                            <p class="font-medium text-xs text-outline">{{ __('admin.no_declined_users') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 6: UNRESPONSIVE USERS LIST -->
            <div x-show="activeTab === 'unresponsive'" class="p-5 space-y-4" style="display: none;">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-outline-variant/40">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.unresponsive_users_tab') }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-mono text-xs font-semibold">
                                {{ $pendingUsersCount ?? count($unresponsiveUsers ?? []) }} {{ __('admin.member') }}
                            </span>
                        </div>
                        <p class="text-xs text-outline">{{ __('admin.unresponsive_users_desc') }}</p>
                    </div>
                    @if ($unresponsiveCount > 0)
                        <a download data-download-button
                            href="{{ route('admin.campaigns.export-detail', [$room, $campaign, 'unresponsive']) }}"
                            @click="downloadExport($event, 'unresponsive')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors no-underline">
                            <span data-download-icon class="material-symbols-outlined text-[16px]">download</span>
                            <span data-download-label>{{ __('admin.download') }}</span>
                        </a>
                    @endif
                </div>

                <div class="overflow-x-auto w-full rounded-xl border border-outline-variant/60">
                    <table class="table-colgroup w-full min-w-[40rem] text-left border-collapse text-xs table-fixed">
                        <thead>
                            <tr class="h-10 bg-surface-container-low border-b border-outline-variant text-outline uppercase font-mono tracking-wider">
                                <th class="px-4 py-2 w-16 text-left">{{ __('admin.order_no') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('admin.member_full_name') }}</th>
                                <th class="px-4 py-2 w-64 text-left">{{ __('admin.member_department') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($unresponsiveUsers as $index => $u)
                                @php
                                    $globalUser = $u->globalUser;
                                    $dept = trim((string) ($globalUser?->desk_location ?? '')) ?: __('admin.unassigned_department');
                                    $name = $globalUser?->name ?? $u->display_name;
                                @endphp
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-4 py-3 w-16 text-left font-mono text-outline">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-left">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-800 flex items-center justify-center font-bold text-xs shrink-0 border border-amber-200">
                                                {{ mb_substr($name, 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-on-surface">{{ $name }}</div>
                                                <div class="text-[10px] font-mono text-outline flex items-center gap-1.5 mt-0.5">
                                                    <span>{{ $globalUser->email }}</span>
                                                    @if ($globalUser->email)
                                                        <button type="button" data-copy="{{ $globalUser->email }}"
                                                            data-copied-message="{{ __('admin.copied') }}"
                                                            data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                                            data-tooltip="{{ __('admin.copy_email') }}"
                                                            aria-label="{{ __('admin.copy_email') }}"
                                                            class="inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                                            <span class="material-symbols-outlined text-[12px]">content_copy</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 w-64 text-left">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-surface-container text-on-surface-variant font-medium text-xs border border-outline-variant/60">
                                            <span class="material-symbols-outlined text-[14px] text-outline">corporate_fare</span>
                                            <span>{{ $dept }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-12 text-center text-outline">
                                        <div class="flex flex-col items-center justify-center gap-2 py-2">
                                            <span class="material-symbols-outlined text-4xl text-outline-variant">task_alt</span>
                                            <p class="font-medium text-xs text-outline">{{ __('admin.no_unresponsive_users') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: DELETE ORDER CONFIRMATION -->
        <div x-show="showDeleteOrderModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @keydown.escape.window="if (!isDeletingOrder) showDeleteOrderModal = false">
            <form @submit.prevent="deleteOrder()" @click.outside="if (!isDeletingOrder) showDeleteOrderModal = false"
                class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-error-container/60 text-error" aria-hidden="true">
                        <span class="material-symbols-outlined text-[21px]">delete</span>
                    </span>
                    <h3 class="text-base font-bold text-on-surface">{{ __('admin.delete_order_title') }}</h3>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-outline">{{ __('admin.confirm_delete_order') }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="showDeleteOrderModal = false" :disabled="isDeletingOrder"
                        class="rounded-lg border border-outline-variant px-3.5 py-2 text-xs font-semibold text-on-surface disabled:opacity-50">{{ __('admin.cancel') }}</button>
                    <button type="submit" :disabled="isDeletingOrder" :aria-busy="isDeletingOrder"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-error px-3.5 py-2 text-xs font-semibold text-white disabled:opacity-60">
                        <span x-show="isDeletingOrder" class="material-symbols-outlined animate-spin text-[16px]" style="display: none;" aria-hidden="true">progress_activity</span>
                        <span x-show="!isDeletingOrder" class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                        <span x-text="isDeletingOrder ? @js(__('admin.processing')) : @js(__('admin.delete_order_btn'))">{{ __('admin.delete_order_btn') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL: CONFIRM ORDER PAYMENT -->
        <div x-show="showConfirmPaymentModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @keydown.escape.window="if (!isConfirmingOrderPayment) showConfirmPaymentModal = false">
            <form @submit.prevent="confirmOrderPayment()" @click.outside="if (!isConfirmingOrderPayment) showConfirmPaymentModal = false"
                class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">
                        <span class="material-symbols-outlined text-[21px]">paid</span>
                    </span>
                    <h3 class="text-base font-bold text-on-surface">{{ __('admin.confirm_order_payment_title') }}</h3>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-outline">{{ __('admin.confirm_order_payment_desc') }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="showConfirmPaymentModal = false" :disabled="isConfirmingOrderPayment"
                        class="rounded-lg border border-outline-variant px-3.5 py-2 text-xs font-semibold text-on-surface disabled:opacity-50">{{ __('admin.cancel') }}</button>
                    <button type="submit" :disabled="isConfirmingOrderPayment" :aria-busy="isConfirmingOrderPayment"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-xs font-semibold text-on-primary disabled:opacity-60">
                        <span x-show="isConfirmingOrderPayment" class="material-symbols-outlined animate-spin text-[16px]" style="display: none;" aria-hidden="true">progress_activity</span>
                        <span x-show="!isConfirmingOrderPayment" class="material-symbols-outlined text-[16px]" aria-hidden="true">paid</span>
                        <span x-text="isConfirmingOrderPayment ? @js(__('admin.processing')) : @js(__('admin.confirm_order_payment_btn'))">{{ __('admin.confirm_order_payment_btn') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL: BULK PAYMENT CONFIRMATION -->
        <div x-show="showBulkConfirmModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @keydown.escape.window="if (!isConfirmingAllOrders) showBulkConfirmModal = false">
            <form @submit.prevent="confirmAllOrders()" @click.outside="if (!isConfirmingAllOrders) showBulkConfirmModal = false"
                class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                        <span class="material-symbols-outlined text-[21px]">payments</span>
                    </span>
                    <h3 class="text-base font-bold text-on-surface">{{ __('admin.confirm_paid_orders_title') }}</h3>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-outline">{{ __('admin.confirm_paid_orders_desc') }}</p>
                <label class="mt-4 block text-xs font-semibold text-on-surface" for="bulk-payment-method">{{ __('admin.payment_method') }}</label>
                <select id="bulk-payment-method" x-model="bulkPaymentMethod" required :disabled="isConfirmingAllOrders"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface">
                    <option value="">{{ __('admin.select_payment_method') }}</option>
                    <option value="cash">{{ __('admin.debt_method_cash') }}</option>
                    <option value="transfer">{{ __('admin.debt_method_transfer') }}</option>
                    <option value="vietqr">{{ __('admin.debt_method_vietqr') }}</option>
                    <option value="room_fund">{{ __('admin.debt_method_room_fund') }}</option>
                </select>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="showBulkConfirmModal = false" :disabled="isConfirmingAllOrders"
                        class="rounded-lg border border-outline-variant px-3.5 py-2 text-xs font-semibold text-on-surface disabled:opacity-50">{{ __('admin.cancel') }}</button>
                    <button type="submit" :disabled="isConfirmingAllOrders" :aria-busy="isConfirmingAllOrders"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-xs font-semibold text-on-primary disabled:opacity-60">
                        <span x-show="isConfirmingAllOrders" class="material-symbols-outlined animate-spin text-[16px]" style="display: none;" aria-hidden="true">progress_activity</span>
                        <span x-show="!isConfirmingAllOrders" class="material-symbols-outlined text-[16px]" aria-hidden="true">payments</span>
                        <span x-text="isConfirmingAllOrders ? @js(__('admin.processing')) : @js(__('admin.confirm_paid_orders'))">{{ __('admin.confirm_paid_orders') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL: ORDER DETAILS -->
        <div x-show="orderDetailModalOpen" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            style="display: none;">
            <div @click.outside="orderDetailModalOpen = false"
                class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <template x-if="selectedOrder">
                    <div class="flex flex-col h-full overflow-hidden">
                        <!-- Modal Header -->
                        <div class="p-4 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container-low shrink-0">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">receipt</span>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-on-surface flex items-center gap-2">
                                        <span>{{ __('admin.order_detail') }}</span>
                                        <span class="text-xs font-mono font-code text-primary" x-text="selectedOrder.code"></span>
                                    </h3>
                                    <p class="text-[11px] text-outline" x-text="selectedOrder.created_at ? ('{{ __('admin.order_created_at') }}: ' + selectedOrder.created_at) : ''"></p>
                                </div>
                            </div>
                            <button type="button" @click="orderDetailModalOpen = false"
                                class="text-outline hover:text-on-surface cursor-pointer p-1 rounded-md hover:bg-surface-container">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-5 space-y-4 text-xs overflow-y-auto grow">
                            <!-- Member Info -->
                            <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-[11px] text-outline block">{{ __('admin.ordered_by') }}</span>
                                    <span class="font-bold text-on-surface text-sm block mt-0.5" x-text="selectedOrder.user_name"></span>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-outline font-mono">
                                        <span x-show="selectedOrder.user_code" x-text="'#' + selectedOrder.user_code"></span>
                                        <span x-show="selectedOrder.email" x-text="selectedOrder.email"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span x-show="selectedOrder.desk_location"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container text-on-surface-variant text-xs font-medium border border-outline-variant/60">
                                        <span class="material-symbols-outlined text-[13px]">location_on</span>
                                        <span x-text="selectedOrder.desk_location"></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="space-y-2">
                                <h4 class="font-bold text-on-surface text-xs uppercase tracking-wider font-mono text-outline">
                                    {{ __('admin.order_items_list') }}
                                </h4>
                                <div class="border border-outline-variant rounded-xl overflow-hidden divide-y divide-outline-variant/40">
                                    <template x-for="(item, idx) in selectedOrder.items" :key="item.id || idx">
                                        <div class="p-3 bg-surface-container-lowest hover:bg-surface-container-low/50 transition-colors">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="space-y-1">
                                                    <div class="font-semibold text-on-surface">
                                                        <span x-text="item.name"></span>
                                                        <span x-show="item.size" class="text-outline font-normal" x-text="'(' + item.size + ')'"></span>
                                                    </div>
                                                    <template x-if="item.toppings && item.toppings.length">
                                                        <div class="flex flex-wrap gap-1 pt-0.5">
                                                            <template x-for="top in item.toppings" :key="top.name">
                                                                <span class="inline-block px-1.5 py-0.5 rounded bg-surface-container text-[10px] text-outline border border-outline-variant/50">
                                                                    + <span x-text="top.name"></span>
                                                                    <span x-show="top.price > 0" x-text="' (' + formatCurrency(top.price) + ')'"></span>
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <div x-show="item.note" class="text-[11px] text-amber-800 bg-amber-50/80 px-2 py-0.5 rounded border border-amber-200/60 mt-1 italic">
                                                        📝 <span x-text="item.note"></span>
                                                    </div>
                                                    <span x-show="item.is_self_paid" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 mt-1">
                                                        <span class="material-symbols-outlined text-[11px]">payments</span>{{ __('admin.self_paid_badge') }}
                                                    </span>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="font-bold text-on-surface font-mono" x-text="formatCurrency(item.line_subtotal || item.total_amount)"></div>
                                                    <div class="text-[11px] text-outline font-mono" x-text="item.quantity + ' x ' + formatCurrency(item.unit_price)"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Order Note if any -->
                            <div x-show="selectedOrder.note" class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 space-y-1">
                                <span class="font-bold text-[11px] flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">description</span>
                                    <span>{{ __('admin.order_note') }}</span>
                                </span>
                                <p class="text-xs" x-text="selectedOrder.note"></p>
                            </div>

                            <!-- Financial Summary -->
                            <div class="bg-surface-container-low border border-outline-variant/60 rounded-xl p-3.5 space-y-2">
                                <div class="flex justify-between items-center text-outline">
                                    <span>{{ __('admin.gross_bill') }}</span>
                                    <span class="font-mono text-on-surface font-semibold" x-text="formatCurrency(selectedOrder.subtotal)"></span>
                                </div>
                                <div x-show="selectedOrder.sponsor_amount > 0" class="flex justify-between items-center text-emerald-700">
                                    <span>{{ __('admin.subsidy') }}</span>
                                    <span class="font-mono font-semibold" x-text="'-' + formatCurrency(selectedOrder.sponsor_amount)"></span>
                                </div>
                                <div class="pt-2 border-t border-outline-variant/40 flex justify-between items-center text-sm font-bold text-on-surface">
                                    <span>{{ __('admin.payable') }}</span>
                                    <span class="font-mono text-primary text-base" x-text="formatCurrency(selectedOrder.final_amount)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- MODAL: DEBT DETAILS -->
        <div x-show="debtDetailModalOpen" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            style="display: none;">
            <div @click.outside="debtDetailModalOpen = false"
                class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <template x-if="selectedDebt">
                    <div class="flex flex-col h-full overflow-hidden">
                        <!-- Modal Header -->
                        <div class="p-4 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container-low shrink-0">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-on-surface flex items-center gap-2">
                                        <span>{{ __('admin.debt_detail') }}</span>
                                        <span class="text-xs font-mono font-code text-primary" x-text="'#' + selectedDebt.code"></span>
                                    </h3>
                                    <p class="text-[11px] text-outline" x-text="selectedDebt.created_at ? ('{{ __('admin.created_at') }}: ' + selectedDebt.created_at) : ''"></p>
                                </div>
                            </div>
                            <button type="button" @click="debtDetailModalOpen = false"
                                class="text-outline hover:text-on-surface cursor-pointer p-1 rounded-md hover:bg-surface-container">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-5 space-y-4 text-xs overflow-y-auto grow">
                            <!-- Member Info -->
                            <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-[11px] text-outline block">{{ __('admin.debt_member') }}</span>
                                    <span class="font-bold text-on-surface text-sm block mt-0.5" x-text="selectedDebt.user_name"></span>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-outline font-mono">
                                        <span x-show="selectedDebt.email" x-text="selectedDebt.email"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span x-show="selectedDebt.desk_location"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container text-on-surface-variant text-xs font-medium border border-outline-variant/60">
                                        <span class="material-symbols-outlined text-[13px]">location_on</span>
                                        <span x-text="selectedDebt.desk_location"></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Transfer Content -->
                            <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl space-y-1">
                                <span class="text-[11px] text-outline block font-medium">{{ __('admin.request_content') }}</span>
                                <div class="text-xs font-mono font-bold text-primary" x-text="selectedDebt.note"></div>
                            </div>

                            <!-- Financial Summary Grid -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl">
                                    <span class="text-[11px] text-outline block">{{ __('admin.original_debt') }}</span>
                                    <span class="text-sm font-bold font-mono text-on-surface mt-0.5 block" x-text="formatCurrency(selectedDebt.original_amount)"></span>
                                </div>
                                <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl">
                                    <span class="text-[11px] text-outline block">{{ __('admin.paid_debt') }}</span>
                                    <span class="text-sm font-bold font-mono text-emerald-700 mt-0.5 block" x-text="formatCurrency(selectedDebt.paid_amount)"></span>
                                </div>
                            </div>

                            <!-- Remaining Balance Card -->
                            <div class="p-4 rounded-xl border flex items-center justify-between"
                                :class="selectedDebt.remaining_amount > 0 ? 'bg-amber-50/80 border-amber-200 text-amber-950' : 'bg-emerald-50/80 border-emerald-200 text-emerald-950'">
                                <div>
                                    <span class="text-[11px] font-medium uppercase tracking-wider block"
                                        :class="selectedDebt.remaining_amount > 0 ? 'text-amber-800' : 'text-emerald-800'">
                                        {{ __('admin.remaining_debt') }}
                                    </span>
                                    <span class="text-xl font-bold font-mono mt-0.5 block"
                                        :class="selectedDebt.remaining_amount > 0 ? 'text-amber-700' : 'text-emerald-700'"
                                        x-text="formatCurrency(selectedDebt.remaining_amount)">
                                    </span>
                                </div>
                                <span class="material-symbols-outlined text-[28px]"
                                    :class="selectedDebt.remaining_amount > 0 ? 'text-amber-500' : 'text-emerald-500'">
                                    account_balance
                                </span>
                            </div>

                            <!-- Status (read-only) -->
                            <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-xl flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-on-surface">{{ __('admin.status') }}:</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-lg border text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-50 text-emerald-800 border-emerald-300': debtsStatus[selectedDebt.id] === 'paid',
                                        'bg-amber-50 text-amber-900 border-amber-300': debtsStatus[selectedDebt.id] === 'unpaid' || debtsStatus[selectedDebt.id] === 'pending',
                                        'bg-blue-50 text-blue-800 border-blue-300': debtsStatus[selectedDebt.id] === 'partial',
                                        'bg-surface-container text-outline border-outline-variant': debtsStatus[selectedDebt.id] === 'waived'
                                    }"
                                    x-text="debtStatusLabels[debtsStatus[selectedDebt.id]] || debtsStatus[selectedDebt.id]"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
    function campaignOrdersComponent() {
        return {
            activeTab: 'aggregated',
            departmentFilter: 'all',
            orderDetailModalOpen: false,
            debtDetailModalOpen: false,
            selectedOrder: null,
            selectedDebt: null,
            isConfirmingAllOrders: false,
            showBulkConfirmModal: false,
            bulkPaymentMethod: @js(\App\Enums\PaymentMethod::Transfer->value),
            showDeleteOrderModal: false,
            isDeletingOrder: false,
            showConfirmPaymentModal: false,
            isConfirmingOrderPayment: false,
            orderActionTarget: null,
            isUpdatingOrder: {},
            isUpdatingDebt: {},
            downloadStates: {},
            tip: { show: false, text: '', x: 0, y: 0 },
            debtStatusLabels: {{ Js::from(collect(\App\Enums\DebtStatus::cases())->mapWithKeys(fn($status) => [$status->value => __('admin.status_' . $status->value)])) }},
            orderStatus: {{ Js::from($orders->mapWithKeys(fn($order) => [(string) $order->id => $order->status->value])) }},
            debtsStatus: {{ Js::from($campaign->debts->mapWithKeys(fn($debt) => [(string) $debt->id => $debt->status instanceof \BackedEnum ? $debt->status->value : (string) $debt->status])) }},
            ordersData: {{ Js::from(
                $orders->map(
                    fn($o) => [
                        'id' => $o->id,
                        'code' => $o->code,
                        'status' => $o->status->value,
                        'created_at' => $o->created_at?->format('d/m/Y H:i') ?? '',
                        'user_name' => $o->roomUser?->display_name ?? __('admin.member'),
                        'user_code' => $o->roomUser?->user_code,
                        'email' => $o->roomUser?->globalUser?->email,
                        'desk_location' => $o->roomUser?->globalUser?->desk_location,
                        'subtotal' => (int) $o->subtotal,
                        'sponsor_amount' => (int) $o->sponsor_amount,
                        'delivery_fee' => (int) ($o->delivery_fee ?? 0),
                        'discount' => (int) ($o->discount ?? 0),
                        'final_amount' => (int) $o->final_amount,
                        'note' => $o->note,
                        'items' => $o->items->map(
                            fn($it) => [
                                'id' => $it->id,
                                'name' => $it->item_name,
                                'size' => $it->size_name,
                                'quantity' => (int) $it->quantity,
                                'unit_price' => (int) $it->unit_price,
                                'total_amount' => (int) $it->total_amount,
                                'line_subtotal' => (int) ($it->line_subtotal ?? $it->total_amount),
                                'note' => $it->note,
                                'is_self_paid' => (bool) $it->is_self_paid,
                                'toppings' => $it->toppings->map(
                                    fn($top) => [
                                        'name' => $top->topping_name,
                                        'price' => (int) $top->unit_price,
                                    ],
                                ),
                            ],
                        ),
                    ],
                ),
            ) }},
            debtsData: {{ Js::from(
                $campaign->debts->map(
                    fn($d) => [
                        'id' => $d->id,
                        'code' => $d->code,
                        'user_name' => $d->roomUser?->display_name ?? __('admin.member'),
                        'email' => $d->roomUser?->globalUser?->email,
                        'desk_location' => $d->roomUser?->globalUser?->desk_location,
                        'original_amount' => (int) $d->original_amount,
                        'sponsor_amount' => (int) ($d->sponsor_amount ?? 0),
                        'paid_amount' => (int) ($d->paid_amount ?? 0),
                        'remaining_amount' => (int) ($d->remaining_amount ?? 0),
                        'status' => $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status,
                        'note' => $d->note ?: $d->code,
                        'created_at' => $d->created_at?->format('d/m/Y H:i') ?? '',
                        'updated_at' => $d->updated_at?->format('d/m/Y H:i') ?? '',
                    ],
                ),
            ) }},

            formatCurrency(val) {
                const num = Number(val) || 0;
                return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
            },

            /** Show the shared tooltip above (or below) the hovered element using its data-tip text. */
            showTip(event) {
                const el = event.currentTarget;
                const text = el?.dataset?.tip;
                if (!text) return;
                this.tip.text = text;
                this.tip.show = true;
                this.$nextTick(() => {
                    const tipEl = this.$refs.tip;
                    if (!tipEl) return;
                    const rect = el.getBoundingClientRect();
                    const w = tipEl.offsetWidth;
                    const h = tipEl.offsetHeight;
                    let left = rect.left + rect.width / 2 - w / 2;
                    left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
                    let top = rect.top - h - 8;
                    if (top < 8) top = rect.bottom + 8;
                    this.tip.x = left;
                    this.tip.y = top;
                });
            },

            hideTip() {
                this.tip.show = false;
            },

            openOrderDetail(orderId) {
                this.selectedOrder = this.ordersData.find(o => o.id === orderId) || null;
                if (this.selectedOrder) {
                    this.orderDetailModalOpen = true;
                }
            },

            openDebtDetail(debtId) {
                this.selectedDebt = this.debtsData.find(d => d.id === debtId) || null;
                if (this.selectedDebt) {
                    this.debtDetailModalOpen = true;
                }
            },

            async copyOrderCode(code, event) {
                try {
                    await navigator.clipboard.writeText(code);
                    if (window.notify) {
                        window.notify('{{ __('admin.copied') }}: ' + code, 'success');
                    }
                } catch (err) {
                    alert('{{ __('admin.copy_failed') }}');
                }
            },

            async toggleOrderConfirmation(orderId) {
                const currentStatus = this.orderStatus[orderId];
                const newStatus = currentStatus === 'confirmed' ? 'submitted' : 'confirmed';

                this.isUpdatingOrder[orderId] = true;
                try {
                    const response = await fetch(`{{ url('admin/' . $room->slug . '/orders') }}/${orderId}/status`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ status: newStatus })
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.status_change_failed') }}');

                    this.orderStatus[orderId] = newStatus;
                    if (window.notify) {
                        window.notify(res.message || '{{ __('admin.status_change_success', ['id' => ':id', 'status' => ':status']) }}'.replace(':id', orderId).replace(':status', newStatus), 'success');
                    }
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    this.isUpdatingOrder[orderId] = false;
                }
            },

            async confirmAllOrders() {
                if (this.isConfirmingAllOrders || !this.bulkPaymentMethod) return;
                this.isConfirmingAllOrders = true;

                try {
                    const response = await fetch(@js(route('admin.campaigns.confirm-debts-paid', [$room, $campaign])), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ payment_method: this.bulkPaymentMethod })
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.status_change_failed') }}');

                    this.showBulkConfirmModal = false;
                    if (window.notify) {
                        window.notify(res.message || '{{ __('admin.bulk_debts_paid_success', ['count' => ':count']) }}'.replace(':count', res.data?.count ?? 0), 'success');
                    }
                    setTimeout(() => window.location.reload(), 800);
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    this.isConfirmingAllOrders = false;
                }
            },

            openDeleteOrderModal(orderId) {
                this.orderActionTarget = orderId;
                this.showDeleteOrderModal = true;
            },

            async deleteOrder() {
                if (!this.orderActionTarget || this.isDeletingOrder) return;
                this.isDeletingOrder = true;
                try {
                    const response = await fetch(`{{ url('admin/' . $room->slug . '/orders') }}/${this.orderActionTarget}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.delete_order_failed') }}');

                    this.showDeleteOrderModal = false;
                    if (window.notify) window.notify('{{ __('admin.order_deleted_success') }}', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    this.isDeletingOrder = false;
                }
            },

            openConfirmPaymentModal(orderId) {
                this.orderActionTarget = orderId;
                this.showConfirmPaymentModal = true;
            },

            async confirmOrderPayment() {
                if (!this.orderActionTarget || this.isConfirmingOrderPayment) return;
                this.isConfirmingOrderPayment = true;
                try {
                    const response = await fetch(`{{ url('admin/' . $room->slug . '/orders') }}/${this.orderActionTarget}/confirm-payment`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.confirm_order_payment_failed') }}');

                    this.showConfirmPaymentModal = false;
                    if (window.notify) window.notify(res.message || '{{ __('admin.confirm_order_payment_success') }}', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    this.isConfirmingOrderPayment = false;
                }
            },

            async updateDebtStatus(debtId, status) {
                this.isUpdatingDebt[debtId] = true;
                try {
                    const response = await fetch(`{{ url('admin/' . $room->slug . '/debts') }}/${debtId}/status`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ status })
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.status_change_failed') }}');

                    this.debtsStatus[debtId] = status;
                    if (this.selectedDebt && this.selectedDebt.id === debtId) {
                        this.selectedDebt.status = status;
                        if (status === 'paid') {
                            this.selectedDebt.paid_amount = this.selectedDebt.original_amount;
                            this.selectedDebt.remaining_amount = 0;
                        }
                    }
                    if (window.notify) {
                        window.notify(res.message || '{{ __('admin.debt_status_updated') }}', 'success');
                    }
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    this.isUpdatingDebt[debtId] = false;
                }
            },

            async downloadExport(event, dataset) {
                event.preventDefault();
                const button = event.currentTarget;
                if (this.downloadStates[dataset]) return;

                const icon = button.querySelector('[data-download-icon]');
                const label = button.querySelector('[data-download-label]');
                const originalIcon = icon?.textContent ?? 'download';
                const originalLabel = label?.textContent ?? '';
                this.downloadStates[dataset] = true;
                if (icon) {
                    icon.textContent = 'progress_activity';
                    icon.classList.add('animate-spin');
                }
                if (label) label.textContent = '{{ __('admin.processing') }}...';
                button.classList.add('opacity-60', 'pointer-events-none');
                button.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(button.href, {
                        headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }
                    });
                    if (!response.ok) throw new Error('{{ __('admin.download_failed') }}');

                    const blob = await response.blob();
                    const contentDisposition = response.headers.get('Content-Disposition') || '';
                    const filenameMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)|filename=([^;]+)/i);
                    const filenameValue = filenameMatch?.[1] || filenameMatch?.[2] || '';
                    const filename = filenameValue ?
                        decodeURIComponent(filenameValue).replaceAll(String.fromCharCode(34), '') :
                        `campaign-${dataset}.xlsx`;
                    const objectUrl = URL.createObjectURL(blob);
                    const downloadLink = document.createElement('a');
                    downloadLink.href = objectUrl;
                    downloadLink.download = filename;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    downloadLink.remove();
                    URL.revokeObjectURL(objectUrl);
                } catch (error) {
                    alert(error.message || '{{ __('admin.download_failed') }}');
                } finally {
                    this.downloadStates[dataset] = false;
                    if (icon) {
                        icon.textContent = originalIcon;
                        icon.classList.remove('animate-spin');
                    }
                    if (label) label.textContent = originalLabel;
                    button.classList.remove('opacity-60', 'pointer-events-none');
                    button.removeAttribute('aria-busy');
                }
            }
        };
    }
    </script>
    @if ($onBehalfData ?? null)
        @include('admin.partials.order-on-behalf-modal', ['room' => $room, 'data' => $onBehalfData])
    @endif
</x-admin.layout>
