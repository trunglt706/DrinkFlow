@php
    $orderI18n = [
        'orderedItems' => __('admin.ordered_items'),
        'selfPaidBadge' => __('admin.self_paid_badge'),
        'ice' => __('admin.pref_ice'),
        'sugar' => __('admin.pref_sugar'),
        'statusPaid' => __('admin.status_paid'),
        'statusUnpaid' => __('admin.status_unpaid'),
        'unitPrice' => __('admin.unit_price'),
        'subtotal' => __('admin.subtotal_label_short'),
        'finalPayable' => __('admin.final_payable_amount'),
        'adjustmentReason' => __('admin.adjustment_reason'),
        'save' => __('admin.save_price_adjustment'),
        'cancel' => __('admin.cancel'),
        'deleteConfirm' => __('admin.confirm_delete_order'),
        'cancelOrderConfirm' => __('admin.cancel_order_confirm'),
        'deleteFailed' => __('admin.delete_order_failed'),
        'updateFailed' => __('admin.update_order_failed'),
        'loadFailed' => __('admin.load_order_failed'),
        'statusChangeSuccess' => __('admin.status_change_success'),
        'statusChangeFailed' => __('admin.status_change_failed'),
        'statusUpdating' => __('admin.status_updating'),
        'statusSubmitted' => __('admin.status_submitted'),
        'statusConfirmed' => __('admin.status_confirmed'),
        'statusCompleted' => __('admin.status_completed'),
        'statusDelivering' => __('admin.status_delivering'),
        'statusCancelled' => __('admin.status_cancelled'),
        'cancelOrderModalTitle' => __('admin.cancel_order_modal_title'),
        'cancelOrderModalDesc' => __('admin.cancel_order_modal_desc'),
        'confirmCancelBtn' => __('admin.confirm_cancel_btn'),
        'cancellingStatus' => __('admin.cancelling_status'),
        'orderCancelledSuccess' => __('admin.order_cancelled_success'),
        'orderCancelledFailed' => __('admin.order_cancelled_failed'),
        'priceAdjustSuccess' => __('admin.price_adjust_success'),
        'savingPriceAdjustment' => __('admin.saving_price_adjustment'),
        'totalOrdersBadge' => __('admin.total_orders_badge'),
        'statusAllFilter' => __('admin.status_all_filter'),
        'thOrderCode' => __('admin.th_order_code'),
        'thCustomer' => __('admin.th_customer'),
        'selectedOrdersCount' => __('admin.selected_orders_count'),
        'bulkActions' => __('admin.bulk_actions'),
        'bulkChangeStatus' => __('admin.bulk_change_status'),
        'bulkApplyStatus' => __('admin.bulk_apply_status'),
        'bulkCancelOrders' => __('admin.bulk_cancel_orders'),
        'bulkCancelConfirmTitle' => __('admin.bulk_cancel_confirm_title'),
        'bulkCancelConfirmDesc' => __('admin.bulk_cancel_confirm_desc'),
        'bulkStatusUpdatedSuccess' => __('admin.bulk_status_updated_success'),
        'bulkCancelledSuccess' => __('admin.bulk_cancelled_success'),
        'deselectAll' => __('admin.deselect_all'),
        'noOrdersSelected' => __('admin.no_orders_selected'),
        'viewOrderDetails' => __('admin.view_order_details'),
        'viewDetails' => __('admin.view_details'),
        'orderDetailModalTitle' => __('admin.order_detail_modal_title'),
        'orderDetailModalSubtitle' => __('admin.order_detail_modal_subtitle'),
        'customerInfo' => __('admin.customer_info'),
        'campaignStoreInfo' => __('admin.campaign_store_info'),
        'campaignDetailUrl' => isset($activeCampaign) && $activeCampaign ? route('admin.campaigns.info', [$room, $activeCampaign]) : '',
        'orderFinancialSummary' => __('admin.order_financial_summary'),
        'orderHistoryTimestamps' => __('admin.order_history_timestamps'),
        'itemNameCol' => __('admin.item_name_col'),
        'itemQtyCol' => __('admin.item_qty_col'),
        'itemUnitPriceCol' => __('admin.item_unit_price_col'),
        'itemTotalCol' => __('admin.item_total_col'),
        'discountVoucher' => __('admin.discount_voucher'),
        'roomSubsidy' => __('admin.room_subsidy'),
        'deliveryFeeOrder' => __('admin.delivery_fee_order'),
        'orderNoteLabel' => __('admin.order_note_label'),
        'orderCreatedTime' => __('admin.order_created_time'),
        'orderCompletedTime' => __('admin.order_completed_time'),
        'orderCancelledTime' => __('admin.order_cancelled_time'),
        'closeModalBtn' => __('admin.close_modal_btn'),
        'adjustPriceBtn' => __('admin.adjust_price_btn'),
        'notAvailable' => __('admin.not_available'),
        'orderedBy' => __('admin.ordered_by'),
        'paymentMethod' => __('admin.payment_method'),
        'paymentStatus' => __('admin.payment_status'),
        'paymentMethodNames' => [
            'transfer' => __('room.campaign.payment_transfer'),
            'qr' => __('admin.debt_method_vietqr'),
            'vietqr' => __('admin.debt_method_vietqr'),
            'cash' => __('admin.debt_method_cash'),
            'room_fund' => __('admin.debt_method_room_fund'),
        ],
    ];
@endphp

<x-admin.layout :title="__('admin.orders_management')" active="orders" :room="$room">
    <div id="admin-orders-page"
         data-i18n="{{ json_encode($orderI18n, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.orders_management') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-surface-container rounded-full text-xs font-mono font-semibold text-secondary">
                {{ __('admin.total_orders_badge', ['count' => $orders->total()]) }}
            </span>
            @if($onBehalfData && $onBehalfData['can_place'])
                <button type="button" data-on-behalf-open class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-white text-xs font-semibold shadow-xs hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-[16px]">person_add</span>
                    <span>{{ __('admin.on_behalf_button') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Live Notice Banner -->
    <div id="notice" class="hidden mb-4 rounded-xl px-4 py-3 text-xs font-medium" role="status" aria-live="polite"></div>

    <!-- Active Live Campaign Card (Above Search Form) -->
    @if(isset($activeCampaign) && $activeCampaign)
        @php
            $deadline = $activeCampaign->deadline ?? $activeCampaign->end_time;
            $hasSponsor = !empty($activeCampaign->sponsor_name) || !empty($activeCampaign->sponsor_type) || !empty($activeCampaign->sponsor_description) || (!empty($activeCampaign->sponsor_allocations) && count((array)$activeCampaign->sponsor_allocations) > 0);
            $payAcc = $activeCampaign->paymentAccount ?? $paymentAccount ?? null;
        @endphp
        <div class="my-4 rounded-2xl bg-surface-container-low border border-outline-variant/70 shadow-xs overflow-hidden">
            <!-- Banner Header -->
            <div class="p-4 bg-surface-container-low/80 border-b border-outline-variant/60 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="relative w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px]">storefront</span>
                        @if($activeCampaign->status instanceof \App\Enums\CampaignStatus && $activeCampaign->status->isRunning())
                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-admin.campaign-status-badge :campaign="$activeCampaign" />
                            <a href="{{ route('admin.campaigns.info', [$room, $activeCampaign]) }}" class="font-bold text-sm text-on-surface truncate hover:text-primary transition-colors no-underline" title="{{ __('admin.view_campaign_details') }}">{{ $activeCampaign->name }}</a>
                            <span class="text-xs bg-surface-container px-2 py-0.5 rounded font-mono font-code font-medium text-secondary">{{ $activeCampaign->code ?? 'N/A' }}</span>
                        </div>
                        <div class="text-xs text-secondary mt-1 flex items-center gap-2 flex-wrap font-medium">
                            <span class="flex items-center gap-1 text-on-surface">
                                <span class="material-symbols-outlined text-[15px] text-outline">restaurant</span>
                                {{ $activeCampaign->restaurant }}
                            </span>
                            @if($activeCampaign->orders_count ?? 0)
                                <span class="text-outline">•</span>
                                <span class="text-primary font-mono font-semibold">{{ $activeCampaign->orders_count }} {{ __('admin.orders') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Mobile: "lock campaign" and "campaign & menu" stretch to share the full row. --}}
                <div class="flex w-full items-center gap-2 lg:w-auto">
                    @if($activeCampaign->isOpenForOrders())
                        @php
                            $isLockedOrdering = $activeCampaign->isOrderingLocked();
                        @endphp
                        <button type="button" data-ordering-lock-toggle
                            data-mode="{{ $isLockedOrdering ? 'unlock' : 'lock' }}"
                            data-url="{{ route($isLockedOrdering ? 'admin.campaigns.unlock-ordering' : 'admin.campaigns.lock-ordering', [$room, $activeCampaign]) }}"
                            title="{{ __('admin.campaign_ordering_locked_hint') }}"
                            class="inline-flex flex-1 justify-center items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-colors shadow-2xs lg:flex-none disabled:opacity-50 disabled:cursor-wait {{ $isLockedOrdering ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-surface-container-high hover:bg-surface-container-highest text-on-surface border-outline-variant/60' }}">
                            <span class="material-symbols-outlined text-[16px] {{ $isLockedOrdering ? '' : 'text-amber-600' }}">{{ $isLockedOrdering ? 'lock_open' : 'lock' }}</span>
                            <span>{{ $isLockedOrdering ? __('admin.campaign_unlock_action') : __('admin.campaign_lock_action') }}</span>
                        </button>
                    @endif
                    <a href="{{ route('admin.campaigns.info', [$room, $activeCampaign]) }}" class="inline-flex flex-1 justify-center items-center gap-1.5 px-3.5 py-2 rounded-xl lg:flex-none bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-xs font-semibold border border-outline-variant/60 transition-colors shadow-2xs">
                        <span class="material-symbols-outlined text-[16px]">campaign</span>
                        <span>{{ __('admin.campaign_menu') }}</span>
                    </a>
                </div>
            </div>

            <!-- Campaign Info Grid -->
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 bg-surface-container-lowest text-xs">
                <!-- 1. Thời gian hết hạn -->
                <div class="p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/50 flex flex-col justify-between">
                    <div class="flex items-center gap-1.5 text-outline text-[11px] font-mono uppercase font-semibold mb-1">
                        <span class="material-symbols-outlined text-[16px] text-amber-600">schedule</span>
                        <span>{{ __('admin.campaign_deadline_label') }}</span>
                    </div>
                    <div>
                        @if($deadline)
                            <div class="font-bold text-on-surface font-mono text-sm">{{ $deadline->format('H:i d/m/Y') }}</div>
                            @if($deadline->isFuture())
                                <div class="text-[11px] text-emerald-600 font-medium mt-0.5">{{ $deadline->diffForHumans() }}</div>
                            @else
                                <div class="text-[11px] text-rose-600 font-medium mt-0.5">{{ __('admin.status_closing') }}</div>
                            @endif
                        @else
                            <div class="font-semibold text-secondary italic">{{ __('admin.campaign_no_deadline') }}</div>
                        @endif
                    </div>
                </div>

                <!-- 2. Ngân sách tối đa & Mô tả -->
                <div class="p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/50 flex flex-col justify-between">
                    <div class="flex items-center gap-1.5 text-outline text-[11px] font-mono uppercase font-semibold mb-1">
                        <span class="material-symbols-outlined text-[16px] text-primary">account_balance_wallet</span>
                        <span>{{ __('admin.campaign_max_budget_label') }}</span>
                    </div>
                    <div>
                        @if($activeCampaign->max_budget && $activeCampaign->max_budget > 0)
                            <div class="font-bold text-primary font-mono text-sm">{{ \App\Support\Helpers\FormatHelper::formatCurrency($activeCampaign->max_budget) }}</div>
                        @else
                            <div class="font-semibold text-secondary">{{ __('admin.campaign_budget_unlimited') }}</div>
                        @endif
                        @if($activeCampaign->description)
                            <div class="text-[11px] text-outline truncate mt-0.5" title="{{ $activeCampaign->description }}">
                                📝 {{ $activeCampaign->description }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Thông tin tài trợ -->
                <div class="p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/50 flex flex-col justify-between">
                    <div class="flex items-center gap-1.5 text-outline text-[11px] font-mono uppercase font-semibold mb-1">
                        <span class="material-symbols-outlined text-[16px] text-purple-600">volunteer_activism</span>
                        <span>{{ __('admin.campaign_sponsor_info_label') }}</span>
                    </div>
                    <div>
                        @if($hasSponsor)
                            <div class="font-bold text-on-surface truncate">{{ $activeCampaign->sponsor_name ?: __('admin.room_subsidy') }}</div>
                            <div class="text-[11px] text-secondary mt-0.5 flex items-center gap-1 flex-wrap">
                                @if($activeCampaign->sponsor_type)
                                    @php
                                        $sponsorTypeKey = 'admin.sponsor_type_' . $activeCampaign->sponsor_type;
                                        $sponsorTypeLabel = __($sponsorTypeKey);
                                    @endphp
                                    <span class="px-1.5 py-0.5 rounded bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 text-[10px] font-semibold">
                                        {{ $sponsorTypeLabel === $sponsorTypeKey ? $activeCampaign->sponsor_type : $sponsorTypeLabel }}
                                    </span>
                                @endif
                                @if($activeCampaign->sponsor_description)
                                    <span class="text-outline truncate">{{ $activeCampaign->sponsor_description }}</span>
                                @endif
                            </div>
                        @else
                            <div class="font-semibold text-secondary italic">{{ __('admin.campaign_no_sponsorship') }}</div>
                        @endif
                    </div>
                </div>

                <!-- 4. Tài khoản nhận thanh toán -->
                <div class="p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/50 flex flex-col justify-between">
                    <div class="flex items-center gap-1.5 text-outline text-[11px] font-mono uppercase font-semibold mb-1">
                        <span class="material-symbols-outlined text-[16px] text-teal-600">payments</span>
                        <span>{{ __('admin.campaign_payment_account_label') }}</span>
                    </div>
                    <div>
                        @if($payAcc)
                            <div class="font-bold text-on-surface truncate flex items-center gap-1.5">
                                <span>{{ $payAcc->bank_name ?: ($payAcc->bank_code ?: 'Bank') }}</span>
                                @if($payAcc->is_default)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">{{ __('admin.default_badge') }}</span>
                                @endif
                            </div>
                            <div class="text-[11px] font-mono text-secondary mt-0.5">
                                <span class="text-outline">{{ __('admin.campaign_account_number_label') }}:</span> <strong class="text-on-surface">{{ $payAcc->account_number }}</strong>
                            </div>
                            <div class="text-[10px] text-outline truncate">
                                <span>{{ __('admin.campaign_account_holder_label') }}:</span> <span class="font-semibold text-secondary uppercase">{{ $payAcc->account_name }}</span>
                            </div>
                        @else
                            <div class="font-semibold text-secondary italic">{{ __('admin.campaign_no_payment_account') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Filter & Search Toolbar -->
    @php
        $hasOrderFilters = trim((string) ($filters['search'] ?? '')) !== ''
            || ((string) ($filters['status'] ?? 'all') !== '' && (string) ($filters['status'] ?? 'all') !== 'all')
            || (string) ($filters['type'] ?? 'all') !== 'all';
    @endphp
    <form id="orders-filter-form" data-skeleton-on-submit method="GET" action="{{ route('admin.orders.page', $room) }}" class="my-4 flex flex-wrap items-center gap-2 bg-surface-container-low p-3 rounded-xl border border-outline-variant/60 lg:justify-between lg:gap-3">
        {{-- Mobile: row 1 = search + status, row 2 = order type + buttons, each full width (the groups use `contents`
             and the hidden break forces the second row). From lg up the original desktop layout is kept. --}}
        <div class="contents lg:flex lg:flex-1 lg:min-w-[260px] lg:items-center lg:gap-2">
            <div class="relative min-w-0 flex-1 basis-0">
                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                <input type="search"
                       name="search"
                       id="order-search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="{{ __('admin.search_orders_placeholder') }}"
                       class="w-full h-9 pl-9 pr-8 bg-surface-container-lowest border border-outline-variant rounded-lg text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>
            <div class="min-w-0 flex-1 basis-0 lg:min-w-[150px] lg:flex-none lg:basis-auto">
                <select name="status"
                        id="order-status-filter"
                        class="w-full h-9 px-3 bg-surface-container-lowest border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('admin.status_all_filter') }}</option>
                    @foreach($statusFilters as $statusFilter)
                        <option value="{{ $statusFilter['value'] }}" {{ ($filters['status'] ?? '') === $statusFilter['value'] ? 'selected' : '' }}>
                            {{ $statusFilter['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="basis-full h-0 lg:hidden" aria-hidden="true"></div>
            <div class="min-w-0 flex-1 basis-0 lg:min-w-[170px] lg:flex-none lg:basis-auto">
                <select name="type"
                        id="order-type-filter"
                        aria-label="{{ __('admin.order_type_filter') }}"
                        class="w-full h-9 px-3 bg-surface-container-lowest border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="all" {{ ($filters['type'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('admin.order_type_all') }}</option>
                    @foreach($typeFilters as $typeFilter)
                        <option value="{{ $typeFilter['value'] }}" {{ ($filters['type'] ?? '') === $typeFilter['value'] ? 'selected' : '' }}>
                            {{ $typeFilter['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="contents lg:flex lg:items-center lg:gap-2">
            <button type="submit" data-icon-only data-tooltip="{{ __('admin.filter_apply') }}" aria-label="{{ __('admin.filter_apply') }}" class="text-xs font-semibold h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg bg-primary text-on-primary hover:bg-primary/90 transition-colors shadow-2xs">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
            </button>
            @if($hasOrderFilters)
                <a href="{{ route('admin.orders.page', $room) }}" class="h-9 shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg border border-outline-variant bg-surface text-secondary text-xs font-semibold hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('admin.filter_clear') }}
                </a>
            @endif
            <x-admin.reload-button />
        </div>
    </form>

    <!-- Orders Table Ledger -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            {{-- Fixed-width side columns; the items-detail column takes the remaining space. --}}
            <table data-skeleton="table" class="table-colgroup w-full min-w-[72rem] table-fixed text-left text-xs border-collapse">
                <colgroup>
                    <col class="w-10">
                    <col class="w-44">
                    {{-- Wide enough for the member's name and email without clipping. --}}
                    <col class="w-72">
                    <col>
                    <col class="w-32">
                    <col class="w-40">
                    <col class="w-24">
                </colgroup>
                <thead>
                    <tr class="bg-surface-container-low text-outline font-mono uppercase text-[11px] border-b border-outline-variant">
                        <th class="py-3 px-3 w-10 text-center">
                            <input type="checkbox"
                                   id="orders-select-all"
                                   class="rounded border-outline-variant text-primary focus:ring-primary w-4 h-4 cursor-pointer"
                                   title="{{ __('admin.select_all_tooltip') }}"
                                   aria-label="{{ __('admin.select_all_tooltip') }}">
                        </th>
                        <th class="py-3 px-4">{{ __('admin.th_order_code') }}</th>
                        <th class="py-3 px-4">{{ __('admin.th_customer') }}</th>
                        <th class="py-3 px-4">{{ __('admin.th_items_detail') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.th_subtotal_actual') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody id="orders-tbody" class="divide-y divide-outline-variant/50">
                    @php
                        $campaignSponsorPercentages = isset($activeCampaign) && $activeCampaign ? $activeCampaign->sponsorPercentages() : [];
                    @endphp
                    @forelse($orders as $ord)
                        @php
                            $member = $ord->roomUser?->globalUser?->name ?? $ord->roomUser?->display_name ?? 'Member #' . $ord->room_user_id;
                            $sponsorPercentage = $campaignSponsorPercentages[(int) $ord->room_user_id] ?? null;
                            $statusValue = $ord->status instanceof \BackedEnum ? $ord->status->value : (string) $ord->status;
                            $stClass = match($statusValue) {
                                'completed', 'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                                'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
                                'submitted', 'pending' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                                'delivering' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800',
                                'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200 line-through dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                                default => 'bg-surface-container text-secondary border-outline-variant'
                            };
                            // One line per ordered item: "2× Name (Size) + Topping, Topping", with its self-paid flag.
                            $itemLines = $ord->items->map(function ($i) {
                                $topps = $i->toppings->pluck('topping_name')->filter()->implode(', ');
                                return [
                                    'text' => $i->quantity . '× ' . $i->item_name . ($i->size_name ? ' (' . $i->size_name . ')' : '') . ($topps ? ' + ' . $topps : ''),
                                    'self_paid' => (bool) $i->is_self_paid,
                                ];
                            });
                            // A child order was placed by another member for this one ("đặt dùm"): every line is on behalf.
                            $isProxyOrder = $ord->parent_id !== null;
                        @endphp
                        <tr class="hover:bg-surface-container-low/50 transition-colors" data-order-row="{{ $ord->id }}">
                            <td class="py-3.5 px-3 w-10 text-center">
                                <input type="checkbox"
                                       class="order-row-checkbox rounded border-outline-variant text-primary focus:ring-primary w-4 h-4 cursor-pointer"
                                       value="{{ $ord->id }}"
                                       data-order-status="{{ $statusValue }}"
                                       aria-label="Select order {{ $ord->code ?? 'N/A' }}">
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-1">
                                    <button type="button"
                                            onclick="openOrderDetailModal({{ $ord->id }})"
                                            class="font-bold text-on-surface text-sm flex items-center gap-1.5 hover:text-primary transition-colors text-left group">
                                        <span class="underline decoration-dotted underline-offset-2 group-hover:decoration-solid font-mono font-code">{{ $ord->code ?? 'N/A' }}</span>
                                        @if($ord->is_locked)
                                            <span class="material-symbols-outlined text-[14px] text-amber-600" title="{{ __('admin.order_locked_tooltip') }}">lock</span>
                                        @endif
                                    </button>
                                    @if($ord->code)
                                        <button type="button" data-copy="{{ $ord->code }}"
                                            data-copied-message="{{ __('admin.copied') }}"
                                            data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                            data-tooltip="{{ __('admin.copy_order_code') }}"
                                            aria-label="{{ __('admin.copy_order_code') }}"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded text-outline hover:text-primary hover:bg-surface-container transition-colors cursor-pointer">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">content_copy</span>
                                        </button>
                                    @endif
                                </div>
                                <div class="text-[11px] font-mono text-outline mt-1">
                                    {{ $ord->created_at ? $ord->created_at->format('H:i d/m/Y') : '' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    @php
                                        $avatar = $ord->roomUser?->globalUser?->avatar_url;
                                        $initial = mb_substr($member, 0, 1);
                                    @endphp
                                    @if($avatar)
                                        <img src="{{ $avatar }}" alt="{{ $member }}" class="w-7 h-7 rounded-full object-cover border border-outline-variant/60 shrink-0" loading="lazy" onerror="this.src='/images/default-avatar.svg'">
                                    @else
                                        <div class="w-7 h-7 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                            {{ $initial }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1 min-w-0">
                                            <div class="font-semibold text-on-surface text-xs truncate" title="{{ $member }}">{{ $member }}</div>
                                            @if($sponsorPercentage !== null)
                                                <span tabindex="0" role="img" data-order-sponsor-mark
                                                    data-tooltip="{{ __('admin.order_sponsor_tooltip', ['percentage' => $sponsorPercentage]) }}"
                                                    aria-label="{{ __('admin.order_sponsor_tooltip', ['percentage' => $sponsorPercentage]) }}"
                                                    class="material-symbols-outlined shrink-0 text-[15px] text-amber-600 cursor-help" style="font-variation-settings:'FILL' 1">volunteer_activism</span>
                                            @endif
                                        </div>
                                        @if($ord->roomUser?->globalUser?->email)
                                            <div class="text-[10px] font-mono text-outline truncate" title="{{ $ord->roomUser->globalUser->email }}" data-order-member-email>{{ $ord->roomUser->globalUser->email }}</div>
                                        @endif
                                        @if($ord->parent?->roomUser)
                                            @php
                                                $proxyBy = $ord->parent->roomUser->globalUser?->name ?? $ord->parent->roomUser->display_name ?? __('admin.member');
                                                $proxyInfo = __('admin.proxy_order_info', ['name' => $proxyBy, 'email' => $ord->parent->roomUser->globalUser?->email ?? '']);
                                            @endphp
                                            <div class="mt-0.5 flex items-center gap-1 text-[10px] text-violet-700 truncate" title="{{ $proxyInfo }}">
                                                <span class="material-symbols-outlined text-[12px] shrink-0">account_tree</span>
                                                <span class="truncate">{{ __('admin.ordered_by') }}: {{ $proxyBy }}</span>
                                            </div>
                                        @endif
                                        @if($ord->placedByAdmin)
                                            @php
                                                $placedBy = __('admin.on_behalf_placed_by', ['admin' => $ord->placedByAdmin->name]);
                                            @endphp
                                            <div class="mt-0.5 flex items-center gap-1 text-[10px] text-sky-700 dark:text-sky-300 truncate" title="{{ $placedBy }}">
                                                <span class="material-symbols-outlined text-[12px] shrink-0">support_agent</span>
                                                <span class="truncate">{{ $placedBy }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($itemLines->isEmpty())
                                    <div class="text-outline">{{ __('admin.ordered_items') }}</div>
                                @else
                                    <ul class="space-y-0.5" data-order-item-lines>
                                        @foreach($itemLines as $itemLine)
                                            <li class="min-w-0">
                                                <div class="text-on-surface font-medium truncate" title="{{ $itemLine['text'] }}">{{ $itemLine['text'] }}</div>
                                                @if($itemLine['self_paid'] || $isProxyOrder)
                                                    <div class="mt-0.5 flex flex-wrap gap-1">
                                                        @if($itemLine['self_paid'])
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-1.5 py-px text-[10px] font-semibold text-amber-800 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-300" data-item-self-paid>
                                                                <span class="material-symbols-outlined text-[11px]">payments</span>{{ __('admin.self_paid_badge') }}
                                                            </span>
                                                        @endif
                                                        @if($isProxyOrder)
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 border border-violet-200 px-1.5 py-px text-[10px] font-semibold text-violet-700 dark:bg-violet-950/40 dark:border-violet-800 dark:text-violet-300" data-item-proxy>
                                                                <span class="material-symbols-outlined text-[11px]">account_tree</span>{{ __('admin.proxy_order_badge') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($ord->note)
                                    <div class="text-[11px] text-amber-700 italic mt-0.5">📝 {{ $ord->note }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($statusValue === 'cancelled')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold border {{ $stClass }}">
                                        {{ __('admin.status_cancelled') }}
                                    </span>
                                @else
                                    <div class="inline-flex items-center relative group">
                                        <select
                                            class="order-status-select appearance-none cursor-pointer text-[11px] font-semibold rounded-lg pl-2.5 pr-6 py-1 border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary/30 shadow-2xs hover:shadow-xs {{ $stClass }}"
                                            data-order-id="{{ $ord->id }}"
                                            data-current-status="{{ $statusValue }}"
                                            aria-label="{{ __('admin.change_status_tooltip') }}"
                                            title="{{ __('admin.change_status_tooltip') }}">
                                            <option value="submitted" {{ $statusValue === 'submitted' ? 'selected' : '' }}>{{ __('admin.status_submitted') }}</option>
                                            <option value="confirmed" {{ $statusValue === 'confirmed' ? 'selected' : '' }}>{{ __('admin.status_confirmed') }}</option>
                                            <option value="completed" {{ $statusValue === 'completed' ? 'selected' : '' }}>{{ __('admin.status_completed') }}</option>
                                            @if($statusValue === 'delivering')
                                                <option value="delivering" selected>{{ __('admin.status_delivering') }}</option>
                                            @endif
                                        </select>
                                        <span class="material-symbols-outlined pointer-events-none absolute right-1 text-[15px] opacity-60 group-hover:opacity-100 transition-opacity">expand_more</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono">
                                <div class="text-xs text-outline line-through">{{ \App\Support\Helpers\FormatHelper::formatCurrency($ord->subtotal_amount ?? 0) }}</div>
                                <div class="font-bold text-sm text-primary" data-order-final-amount="{{ $ord->id }}">{{ \App\Support\Helpers\FormatHelper::formatCurrency($ord->final_amount ?? $ord->subtotal_amount ?? 0) }}</div>
                                @if($ord->sponsor_amount > 0)
                                    <div class="text-[10px] text-emerald-600">-{{ \App\Support\Helpers\FormatHelper::formatCurrency($ord->sponsor_amount) }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button"
                                            onclick="openOrderDetailModal({{ $ord->id }})"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-secondary hover:text-primary hover:bg-surface-container transition-colors"
                                            title="{{ __('admin.view_order_details') }}"
                                            aria-label="{{ __('admin.view_order_details') }}">
                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>
                                    @if($statusValue !== 'cancelled')
                                        <details class="relative inline-block text-left">
                                            <summary class="list-none cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg text-secondary hover:text-primary hover:bg-surface-container transition-colors" aria-label="{{ __('admin.th_actions') }}">
                                                <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                            </summary>
                                            <div class="absolute right-0 mt-1 w-48 z-20 rounded-xl border border-outline-variant bg-surface-container-lowest shadow-xl p-1.5 space-y-0.5">
                                                @if($onBehalfData && \App\Enums\OrderStatus::tryFrom($statusValue)?->isActive() &&! in_array($ord->payment_status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::Pending], true))
                                                    <button type="button" data-order-edit="{{ $ord->id }}" data-order-code="{{ $ord->code }}" data-order-member="{{ $member }}" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left">
                                                        <span class="material-symbols-outlined text-[16px] text-primary">edit_note</span>{{ __('admin.edit_order_btn') }}
                                                    </button>
                                                @endif
                                                <button type="button" onclick="openPriceAdjustmentModal({{ $ord->id }}); this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left">
                                                    <span class="material-symbols-outlined text-[16px] text-primary">tune</span>{{ __('admin.adjust_price_btn') }}
                                                </button>
                                                @if($ord->is_locked)
                                                    <button type="button" onclick="unlockOrder({{ $ord->id }}); this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left">
                                                        <span class="material-symbols-outlined text-[16px] text-amber-600">lock_open</span>{{ __('admin.unlock_order_btn') }}
                                                    </button>
                                                @endif
                                                <button type="button" onclick="openCancelOrderModal({{ $ord->id }}, '{{ addslashes($ord->code ?? 'N/A') }}', '{{ addslashes($member) }}', '{{ \App\Support\Helpers\FormatHelper::formatCurrency($ord->final_amount ?? $ord->subtotal_amount ?? 0) }}'); this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-left">
                                                    <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span>{{ __('admin.cancel_order_btn') }}
                                                </button>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-outline">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-outline-variant">local_shipping</span>
                                    <p class="font-medium text-sm">{{ __('admin.no_orders_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="p-4 border-t border-outline-variant">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- Floating Bulk Actions Toolbar -->
    {{-- Mobile (below lg): pinned to both screen edges and wrapped into rows (count + deselect / status + apply /
         cancel) instead of one centered row wider than the screen; lg+ unchanged. --}}
    <div id="orders-bulk-bar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-surface-container-highest/95 backdrop-blur-md border border-outline-variant rounded-2xl shadow-2xl p-3 px-5 flex items-center gap-4 text-xs animate-in fade-in slide-in-from-bottom-4 duration-200 max-lg:z-[45] max-lg:bottom-3 max-lg:left-3 max-lg:right-3 max-lg:translate-x-0 max-lg:flex-wrap max-lg:gap-2 max-lg:px-3">
        <div class="flex items-center gap-2 font-semibold text-on-surface max-lg:order-1 max-lg:min-w-0 max-lg:flex-1">
            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
            <span id="bulk-selected-count">0 {{ __('admin.selected_orders_count', ['count' => 0]) }}</span>
        </div>

        <div class="h-4 w-px bg-outline-variant max-lg:hidden"></div>

        <!-- Bulk Status Selector & Apply -->
        <div class="flex items-center gap-1.5 max-lg:order-3 max-lg:basis-full">
            <select id="bulk-status-select" class="h-8 px-2.5 max-lg:min-w-0 max-lg:flex-1 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="submitted">{{ __('admin.status_submitted') }}</option>
                <option value="confirmed">{{ __('admin.status_confirmed') }}</option>
                <option value="completed">{{ __('admin.status_completed') }}</option>
            </select>
            <button type="button" id="bulk-apply-status-btn" class="h-8 shrink-0 px-3 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-colors inline-flex items-center gap-1 shadow-2xs">
                <span class="material-symbols-outlined text-[15px]" id="bulk-apply-icon">done_all</span>
                <span id="bulk-apply-text">{{ __('admin.bulk_apply_status') }}</span>
            </button>
        </div>

        <div class="h-4 w-px bg-outline-variant max-lg:hidden"></div>

        <!-- Bulk Cancel Button -->
        <button type="button" id="bulk-cancel-btn" class="h-8 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition-colors inline-flex items-center gap-1 shadow-2xs max-lg:order-4 max-lg:basis-full max-lg:justify-center">
            <span class="material-symbols-outlined text-[15px]">cancel</span>
            <span>{{ __('admin.bulk_cancel_orders') }}</span>
        </button>

        <!-- Deselect All Button -->
        <button type="button" id="bulk-deselect-btn" class="max-lg:order-2 text-outline hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition-colors" title="{{ __('admin.deselect_all') }}">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>

    <!-- Modal Order Details -->
    <div id="order-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div id="order-detail-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-2xl bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant bg-surface-container-low/60 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                    </div>
                    <div>
                        <div class="flex flex-col items-start gap-1 lg:flex-row lg:items-center lg:gap-2">
                            <h3 class="font-bold text-base text-on-surface" id="detail-modal-title">{{ __('admin.order_detail_modal_title', ['id' => '']) }}</h3>
                            <span id="detail-modal-status-badge" class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold border"></span>
                        </div>
                        <p class="text-[11px] text-outline mt-0.5">{{ __('admin.order_detail_modal_subtitle') }}</p>
                    </div>
                </div>
                <button type="button" onclick="closeOrderDetailModal()" class="w-8 h-8 flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors" aria-label="{{ __('admin.close_modal_btn') }}">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div id="order-detail-modal-body" class="p-6 overflow-y-auto space-y-4 text-xs">
                <!-- Dynamically populated via JS -->
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 border-t border-outline-variant bg-surface-container-low/40 flex items-center justify-between shrink-0">
                <div id="detail-modal-quick-actions" class="flex items-center gap-2">
                    <!-- Dynamic quick actions like Adjust Price -->
                </div>
                <button type="button" onclick="closeOrderDetailModal()" class="px-4 py-2 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-xl text-xs font-semibold transition-colors">
                    {{ __('admin.close_modal_btn') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Price Adjustment -->
    <div id="price-adjust-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
        <div id="modal-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-2xl bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-outline-variant mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded bg-primary text-on-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">tune</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-on-surface" id="modal-order-title">{{ __('admin.adjust_price_title', ['id' => '']) }}</h3>
                        <p class="text-xs text-outline">{{ __('admin.adjust_reason') }}</p>
                    </div>
                </div>
                <button type="button" onclick="closePriceAdjustModal()" class="text-outline hover:text-on-surface p-1">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div id="modal-body" class="space-y-4 text-xs">
                <!-- Dynamically rendered via JS -->
            </div>
        </div>
    </div>

    <!-- Modal Cancel Order Confirmation -->
    <div id="cancel-order-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
        <div id="cancel-modal-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-start gap-3.5 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">cancel</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-base text-on-surface">{{ __('admin.cancel_order_modal_title') }}</h3>
                    <p class="text-xs text-outline mt-1">{{ __('admin.cancel_order_modal_desc') }}</p>
                </div>
                <button type="button" onclick="closeCancelOrderModal()" class="text-outline hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="bg-surface-container-low rounded-xl p-3.5 border border-outline-variant/60 mb-5 space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-outline">{{ __('admin.th_order_code') }}:</span>
                    <span class="font-bold text-on-surface font-mono" id="cancel-modal-order-code">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-outline">{{ __('admin.th_customer') }}:</span>
                    <span class="font-semibold text-on-surface" id="cancel-modal-member-name">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-outline">{{ __('admin.th_subtotal_actual') }}:</span>
                    <span class="font-bold text-primary font-mono" id="cancel-modal-amount">0đ</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeCancelOrderModal()" id="cancel-order-modal-close-btn" class="px-4 py-2 text-xs font-semibold rounded-xl border border-outline-variant text-on-surface hover:bg-surface-container transition-colors">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button" id="confirm-cancel-order-btn" class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]" id="confirm-cancel-icon">cancel</span>
                    <span id="confirm-cancel-btn-text">{{ __('admin.confirm_cancel_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Bulk Cancel Orders Confirmation -->
    <div id="bulk-cancel-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
        <div id="bulk-cancel-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-start gap-3.5 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">cancel</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-base text-on-surface">{{ __('admin.bulk_cancel_confirm_title') }}</h3>
                    <p class="text-xs text-outline mt-1" id="bulk-cancel-modal-desc">{{ __('admin.bulk_cancel_confirm_desc', ['count' => 0]) }}</p>
                </div>
                <button type="button" onclick="closeBulkCancelModal()" class="text-outline hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="flex items-center justify-end gap-2.5 mt-5">
                <button type="button" onclick="closeBulkCancelModal()" id="bulk-cancel-modal-close-btn" class="px-4 py-2 text-xs font-semibold rounded-xl border border-outline-variant text-on-surface hover:bg-surface-container transition-colors">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button" id="confirm-bulk-cancel-btn" class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs inline-flex items-center gap-1.5 transition-all">
                    <span class="material-symbols-outlined text-[16px]" id="confirm-bulk-cancel-icon">cancel</span>
                    <span id="confirm-bulk-cancel-btn-text">{{ __('admin.confirm_cancel_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    @if($onBehalfData)
        @include('admin.partials.order-on-behalf-modal', ['room' => $room, 'data' => $onBehalfData])
    @endif
    @if(isset($activeCampaign) && $activeCampaign && $activeCampaign->isOpenForOrders())
        <x-admin.campaign-ordering-lock-modal />
    @endif
    </div>
</x-admin.layout>
