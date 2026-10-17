<x-admin.layout :title="__('admin.debts_management')" active="debts" :room="$room">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.debts_management') }}</h1>
        </div>
        <div class="flex items-center gap-2.5">
            <x-admin.reload-button :header="true" />
            <button type="button" onclick="exportDebtCSV()" class="px-3.5 py-2 bg-surface-container hover:bg-surface-container-high border border-outline-variant text-on-surface rounded text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors">
                <span class="material-symbols-outlined text-[16px] text-primary">download</span>
                <span>{{ __('admin.export_csv_btn') }}</span>
            </button>
        </div>
    </div>

    <!-- 4 Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.total_store_spending') }}</span>
                <span class="material-symbols-outlined text-[20px] text-secondary">receipt_long</span>
            </div>
            <div class="text-2xl font-bold font-mono text-on-surface mt-2">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalSpent ?? 0) }}</div>
            <div class="text-[11px] text-outline mt-1">{{ __('admin.kpi_total_spent_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.total_member_collected') }}</span>
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 mt-2">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalCollected ?? 0) }}</div>
            <div class="text-[11px] text-emerald-700 mt-1">{{ __('admin.kpi_collected_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.store_debt_pending') }}</span>
                <span class="material-symbols-outlined text-[20px] text-blue-600">storefront</span>
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 mt-2">{{ \App\Support\Helpers\FormatHelper::formatCurrency($storeDebtPending ?? 0) }}</div>
            <div class="text-[11px] text-outline mt-1">{{ __('admin.kpi_store_debt_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.member_debt_remaining') }}</span>
                <span class="material-symbols-outlined text-[20px] text-amber-600">pending_actions</span>
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 mt-2">{{ \App\Support\Helpers\FormatHelper::formatCurrency($memberDebtRemaining ?? 0) }}</div>
            <div class="text-[11px] text-amber-700 mt-1">{{ __('admin.kpi_member_debt_desc') }}</div>
        </div>
    </div>

    <!-- Filter and Search Toolbar -->
    <form id="debts-filter-form" data-skeleton-on-submit method="GET" action="{{ route('admin.debts.page', $room) }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-wrap items-center justify-between gap-2 shadow-xs lg:gap-3">
        {{-- Mobile (below lg): search on its own row, then member + status, then date range + filter/clear buttons, each
             row full width. The filter group uses `contents` there and `order` rearranges its items; lg+ is unchanged. --}}
        <div class="flex items-center gap-3 flex-1 min-w-[280px] max-lg:basis-full">
            <x-admin.search-input id="debt-search" name="search" :value="$filters['search'] ?? ''" placeholder="{{ __('admin.search_debts_placeholder') }}" containerClass="relative w-full max-w-md" />
        </div>
        <div class="contents lg:flex lg:items-center lg:gap-2 lg:flex-wrap">
            <div class="order-1 min-w-0 flex-1 basis-[calc(50%-0.25rem)] lg:order-none lg:w-60 lg:flex-none lg:basis-auto">
                <select id="debt-user-filter" name="user" data-searchable="true"
                    data-placeholder="{{ __('admin.debt_filter_user_search_placeholder') }}"
                    data-empty-text="{{ __('admin.debt_filter_user_empty') }}"
                    aria-label="{{ __('admin.debt_filter_user') }}"
                    class="h-9 px-3 bg-surface border border-outline-variant rounded text-xs text-on-surface">
                    <option value="">{{ __('admin.debt_filter_all_users') }}</option>
                    @foreach($memberFilters as $memberFilter)
                        <option value="{{ $memberFilter['value'] }}" data-search="{{ $memberFilter['search'] }}" {{ (string) ($filters['user'] ?? '') === (string) $memberFilter['value'] ? 'selected' : '' }}>{{ $memberFilter['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="order-4 min-w-0 flex-1 basis-0 lg:order-none lg:flex-none lg:basis-auto max-lg:[&>[data-date-range-picker]]:block max-lg:[&>[data-date-range-picker]]:w-full max-lg:[&_[data-date-range-trigger]]:w-full max-lg:[&_[data-date-range-trigger]]:justify-between max-lg:[&_[data-date-range-label]]:truncate max-lg:[&_[data-date-range-label]]:whitespace-nowrap max-lg:[&_[data-date-range-dropdown]]:left-0 max-lg:[&_[data-date-range-dropdown]]:right-auto">
                <x-admin.date-range-filter id="debt-date-range" :date-from="$filters['date_from'] ?? ''" :date-to="$filters['date_to'] ?? ''" form-id="debts-filter-form" />
            </div>
            <select id="debt-status-filter" name="status" class="order-2 h-9 min-w-0 flex-1 basis-[calc(50%-0.25rem)] px-3 bg-surface border border-outline-variant rounded text-xs text-on-surface lg:order-none lg:flex-none lg:basis-auto">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('admin.filter_all') }}</option>
                @foreach($statusFilters as $statusFilter)
                    <option value="{{ $statusFilter['value'] }}" {{ ($filters['status'] ?? '') === $statusFilter['value'] ? 'selected' : '' }}>{{ $statusFilter['label'] }}</option>
                @endforeach
            </select>
            <div class="order-3 basis-full h-0 lg:hidden" aria-hidden="true"></div>
            <button type="submit" data-icon-only data-tooltip="{{ __('admin.filter_apply') }}" aria-label="{{ __('admin.filter_apply') }}" class="order-5 lg:order-none text-xs font-semibold h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg bg-primary text-on-primary hover:bg-primary-container transition-colors">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
            </button>
            @if(trim((string) ($filters['search'] ?? '')) !== '' || ($filters['status'] ?? 'all') !== 'all' || ($filters['user'] ?? '') !== '' || ($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '')
                <a id="debt-clear-filters" href="{{ route('admin.debts.page', $room) }}" class="order-6 lg:order-none h-9 shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg border border-outline-variant bg-surface text-on-surface text-xs font-semibold hover:bg-surface-container transition-colors no-underline">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('admin.filter_clear') }}
                </a>
            @endif
        </div>
    </form>

    <!-- Consolidated payment requests are listed in the ledger below, in place of the debts they bundle. -->
    @php
        $requestStatusClass = [
            'pending' => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        ];
    @endphp
    @if(($pendingRequestCount ?? 0) > 0)
        <a href="{{ route('admin.debts.page', [$room, 'status' => \App\Enums\DebtStatus::Pending->value]) }}"
            class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-900 no-underline hover:bg-amber-100 transition-colors" data-pending-requests-notice>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">hourglass_top</span>
            {{ __('admin.payment_requests_pending_notice', ['count' => $pendingRequestCount]) }}
        </a>
    @endif

    <!-- Debts Table Ledger -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            {{-- Fixed-width member/amount columns; the origin-campaign column takes the remaining space. --}}
            <table data-skeleton="table" class="table-colgroup w-full min-w-[72rem] table-fixed text-left text-xs border-collapse">
                <colgroup>
                    <col class="w-56">
                    <col class="min-w-[18rem]">
                    <col class="w-28">
                    <col class="w-32">
                    <col class="w-36">
                    <col class="w-48">
                </colgroup>
                <thead>
                    <tr class="bg-surface-container-low text-outline font-mono uppercase text-[11px] border-b border-outline-variant">
                        <th class="py-3 px-4">{{ __('admin.debt_member') }}</th>
                        <th class="py-3 px-4">{{ __('admin.origin_campaign') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.th_original_debt') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.remaining_debt') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody id="debts-tbody" class="divide-y divide-outline-variant/50">
                    @forelse($debts as $debt)
                        @if ($debt->campaign_id === null && isset($paymentRequestRows[$debt->id]))
                            {{-- Consolidated payment request (parent): stands for the campaign debts bundled into it. --}}
                            @php $paymentRequest = $paymentRequestRows[$debt->id]; @endphp
                            <tr id="payment-request-{{ $debt->id }}" class="hover:bg-surface-container-low/50 transition-colors bg-primary/[0.02]"
                                data-debt-row data-payment-request-row="{{ $debt->id }}" data-debt-id="{{ $debt->id }}" data-status="{{ $paymentRequest['status'] }}">
                                <td class="py-3.5 px-4">
                                    <button type="button" data-open-payment-request
                                        data-payment-request="{{ json_encode($paymentRequest, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                        class="font-bold text-on-surface text-sm text-left hover:text-primary transition-colors cursor-pointer">{{ $paymentRequest['member'] }}</button>
                                    <div class="text-secondary text-[11px] mt-0.5">{{ $paymentRequest['member_meta'] }}</div>
                                    <div class="mt-0.5 text-[11px] font-mono text-outline">{{ $paymentRequest['code'] }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-on-surface flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-primary" aria-hidden="true">stacks</span>
                                        {{ __('admin.payment_request_ledger_label', ['count' => count($paymentRequest['debts'])]) }}
                                        @if($paymentRequest['conflict'])
                                            <span class="material-symbols-outlined text-[15px] text-rose-600" data-tooltip="{{ __('admin.payment_request_conflict_notice', ['expected' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentRequest['amount']), 'actual' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentRequest['current_total'])]) }}" aria-hidden="true">warning</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-outline truncate" title="{{ collect($paymentRequest['debts'])->pluck('campaign')->unique()->implode(', ') }}">
                                        {{ collect($paymentRequest['debts'])->pluck('campaign')->unique()->implode(', ') }}
                                    </div>
                                    <div class="text-[11px] text-outline">{{ $paymentRequest['requested_at'] }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full whitespace-nowrap text-[11px] font-semibold border {{ $requestStatusClass[$paymentRequest['status']] ?? 'bg-surface-container text-secondary border-outline-variant' }}" data-debt-status-badge>
                                        {{ $paymentRequest['status_label'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-secondary">
                                    {{ \App\Support\Helpers\FormatHelper::formatCurrency($paymentRequest['amount']) }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-sm {{ $paymentRequest['status'] === 'pending' ? 'text-amber-600' : 'text-emerald-600' }}">
                                    {{-- Only a pending request is still owed; an approved one is settled, a rejected one owes nothing itself. --}}
                                    {{ \App\Support\Helpers\FormatHelper::formatCurrency($paymentRequest['status'] === 'pending' ? $paymentRequest['amount'] : 0) }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <button type="button" data-open-payment-request
                                        data-payment-request="{{ json_encode($paymentRequest, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                        class="px-2.5 py-1 rounded text-[11px] font-bold inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer {{ $paymentRequest['status'] === 'pending' ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-surface-container text-primary border border-outline-variant/60 hover:bg-surface-container-high' }}">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $paymentRequest['status'] === 'pending' ? 'pending_actions' : 'visibility' }}</span>
                                        <span>{{ $paymentRequest['status'] === 'pending' ? __('admin.payment_request_view_btn') : __('admin.payment_request_view_detail') }}</span>
                                    </button>
                                </td>
                            </tr>
                            @continue
                        @endif
                        @php
                            $member        = $debt->roomUser?->globalUser?->name ?? $debt->roomUser?->display_name ?? 'Member #' . $debt->room_user_id;
                            $memberEmail   = $debt->roomUser?->globalUser?->email ?? '';
                            $memberCode    = $debt->roomUser?->user_code ?? '';
                            $campaignName  = $debt->campaign?->name ?? 'N/A';
                            $updatedAt     = $debt->payment_requested_at ? $debt->payment_requested_at->format('H:i d/m/Y') : ($debt->updated_at ? $debt->updated_at->format('H:i d/m/Y') : '');
                            $createdAt     = $debt->created_at ? $debt->created_at->format('H:i d/m/Y') : '';
                            $transferContent = $debt->payment_content ?: $debt->code;
                            $debtStatusValue = $debt->status instanceof \BackedEnum ? $debt->status->value : (string) $debt->status;
                            $bundleRequest = $debtRequests[$debt->id] ?? null;
                            $bundleStatus = $bundleRequest?->getStatusValue();
                            $isLockedByRequest = in_array($bundleStatus, [\App\Enums\DebtStatus::Pending->value, \App\Enums\DebtStatus::Approved->value], true);
                            $isPayAll      = !empty($debt->payment_content) && $debt->roomUser && $debt->payment_content === $debt->roomUser->user_code;
                            $userPendingDebts = ($isPayAll && $debt->roomUser) ? $debt->roomUser->debts->map(fn($d) => [
                                'id' => $d->id,
                                'code' => $d->code ?? 'N/A',
                                'campaign' => $d->campaign?->name ?? 'N/A',
                                'amount' => (int) ($d->remaining_amount > 0 ? $d->remaining_amount : $d->original_amount),
                                'note' => $d->note,
                            ])->values() : collect();
                            $totalPendingAmount = $isPayAll ? (int) $userPendingDebts->sum('amount') : (int) $debt->remaining_amount;
                            $stClass = match($debtStatusValue) {
                                'paid'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'pending' => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
                                'partial' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'unpaid'  => 'bg-amber-50 text-amber-700 border-amber-200 font-bold',
                                default   => 'bg-surface-container text-secondary border-outline-variant'
                            };
                            $stIcon = match($debtStatusValue) {
                                'paid'    => 'check_circle',
                                'pending' => 'hourglass_top',
                                'partial' => 'timelapse',
                                'unpaid'  => 'error',
                                'waived'  => 'volunteer_activism',
                                default   => 'help',
                            };
                        @endphp
                        <tr class="hover:bg-surface-container-low/50 transition-colors"
                            data-debt-row
                            data-debt-id="{{ $debt->id }}"
                            data-status="{{ $debtStatusValue }}"
                            data-search="{{ strtolower($member . ' ' . $debt->id . ' ' . $campaignName) }}">
                            <td class="py-3.5 px-4">
                                <button type="button" data-open-debt-detail
                                    data-debt-detail="{{ json_encode($debtDetails[$debt->id] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                    class="font-bold text-on-surface text-sm text-left hover:text-primary transition-colors cursor-pointer">{{ $member }}</button>
                                <div class="text-secondary text-[11px] mt-0.5">{{ $memberEmail }}</div>
                                <div class="mt-0.5 flex items-center gap-1 text-[11px] font-mono text-outline">
                                    <span>{{ $debt->code ?? 'N/A' }}</span>
                                    @if ($debt->code)
                                        <button type="button" data-copy="{{ $debt->code }}"
                                            data-copied-message="{{ __('admin.copied') }}"
                                            data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                            data-tooltip="{{ __('admin.copy_debt_code') }}"
                                            aria-label="{{ __('admin.copy_debt_code') }}"
                                            class="inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                            <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($debt->campaign)
                                    <a href="{{ route('admin.campaigns.info', [$room, $debt->campaign]) }}"
                                        class="font-semibold text-on-surface hover:text-primary transition-colors no-underline">{{ $debt->campaign->name }}</a>
                                @else
                                    <div class="font-semibold text-on-surface">N/A</div>
                                @endif
                                <div class="text-[11px] text-outline">{{ $debt->created_at ? $debt->created_at->format('H:i d/m/Y') : '' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center gap-1 pl-1.5 pr-2.5 py-0.5 rounded-full whitespace-nowrap text-[11px] font-semibold border {{ $stClass }}" data-debt-status-badge>
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $stIcon }}</span>
                                    {{ __('admin.status_' . $debtStatusValue) }}
                                </span>
                                @if($bundleRequest)
                                    <a href="#payment-request-{{ $bundleRequest->id }}" class="mt-1 flex items-center justify-center gap-0.5 text-[10px] font-semibold no-underline {{ $bundleStatus === \App\Enums\DebtStatus::Pending->value ? 'text-amber-700' : ($bundleStatus === \App\Enums\DebtStatus::Rejected->value ? 'text-rose-600' : 'text-emerald-700') }}">
                                        <span class="material-symbols-outlined text-[12px]" aria-hidden="true">stacks</span>
                                        {{ __('admin.debt_in_payment_request', ['code' => $bundleRequest->code]) }}
                                    </a>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-secondary">
                                {{ \App\Support\Helpers\FormatHelper::formatCurrency($debt->original_amount ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-sm {{ $debt->remaining_amount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ \App\Support\Helpers\FormatHelper::formatCurrency($debt->remaining_amount ?? 0) }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button" data-open-debt-detail
                                        data-debt-detail="{{ json_encode($debtDetails[$debt->id] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                        data-tooltip="{{ __('admin.view_debt_detail') }}"
                                        aria-label="{{ __('admin.view_debt_detail') }}"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-surface-container hover:bg-surface-container-high text-primary border border-outline-variant/60 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    </button>
                                    @if($debtStatusValue === 'pending')
                                        <button
                                            type="button"
                                            class="px-2.5 py-1 bg-amber-500 text-white hover:bg-amber-600 rounded text-[11px] font-bold flex items-center gap-1 shadow-2xs transition-colors cursor-pointer"
                                            onclick="openApproveDebtModal({
                                                id: {{ $debt->id }},
                                                member: @js($member),
                                                memberEmail: @js($memberEmail),
                                                memberCode: @js($memberCode),
                                                campaign: @js($campaignName),
                                                amount: {{ (int) ($isPayAll ? $totalPendingAmount : $debt->remaining_amount) }},
                                                updatedAt: @js($updatedAt),
                                                createdAt: @js($createdAt),
                                                transferContent: @js($transferContent),
                                                isPayAll: {{ $isPayAll ? 'true' : 'false' }},
                                                pendingDebts: {{ Js::from($userPendingDebts) }}
                                            })"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">pending_actions</span>
                                            <span>{{ __('admin.approve_payment_btn') }}</span>
                                        </button>
                                    @endif
                                    @if($debt->remaining_amount > 0 && $debtStatusValue !== 'pending' && ! $isLockedByRequest)
                                        <button type="button" onclick="openRecordPaymentModal({{ $debt->id }}, {{ $debt->remaining_amount }}, @js($member), @js($campaignName))" class="px-2.5 py-1 bg-emerald-600 text-white hover:bg-emerald-700 rounded text-[11px] font-semibold flex items-center gap-1 shadow-2xs transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">payments</span>
                                            <span>{{ __('admin.record_payment_btn') }}</span>
                                        </button>
                                        <button type="button" onclick="openAdjustDebtModal({{ $debt->id }}, {{ $debt->remaining_amount }}, @js($member), @js($campaignName))"
                                            data-tooltip="{{ __('admin.adjust_debt_tooltip') }}"
                                            aria-label="{{ __('admin.adjust_debt_btn') }}"
                                            class="inline-flex items-center justify-center w-7 h-7 text-secondary hover:text-primary rounded-lg hover:bg-surface-container transition-colors cursor-pointer">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">tune</span>
                                        </button>
                                    @elseif($debt->remaining_amount <= 0)
                                        <span class="text-[11px] text-emerald-700 font-semibold flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[14px]">verified</span>
                                            <span>{{ __('admin.settled_badge') }}</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-outline">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-outline-variant">payments</span>
                                    <p class="font-medium text-sm">{{ __('admin.no_debts_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($debts->hasPages())
            <div class="p-4 border-t border-outline-variant">
                {{ $debts->links() }}
            </div>
        @endif
    </div>

    <!-- Generic Debt Action Modal (Record Payment / Adjust) -->
    @php
        $debtModalI18n = [
            'confirmTitle' => __('admin.debt_modal_confirm_title'),
            'adjustTitle' => __('admin.debt_modal_adjust_title'),
            'paymentAmount' => __('admin.payment_amount'),
            'paymentMethod' => __('admin.payment_method'),
            'methodVietqr' => __('admin.debt_method_vietqr'),
            'methodCash' => __('admin.debt_method_cash'),
            'methodRoomFund' => __('admin.debt_method_room_fund'),
            'reference' => __('admin.ref_code'),
            'confirmPayment' => __('admin.confirm_payment'),
            'adjustType' => __('admin.adjust_type'),
            'adjustDecrease' => __('admin.debt_adjust_decrease'),
            'adjustIncrease' => __('admin.debt_adjust_increase'),
            'adjustWaive' => __('admin.debt_adjust_waive'),
            'adjustCorrection' => __('admin.debt_adjust_correction'),
            'amount' => __('admin.debt_modal_amount'),
            'reason' => __('admin.debt_modal_reason'),
            'saveAdjustment' => __('admin.save_debt_adjustment'),
            'cancel' => __('admin.cancel'),
            'amountExceeds' => __('admin.debt_modal_amount_exceeds'),
            'paymentError' => __('admin.debt_modal_payment_error'),
            'adjustError' => __('admin.debt_modal_adjust_error'),
            'serverError' => __('admin.debt_modal_server_error'),
            'approveTitle' => __('admin.payment_approval_modal_title'),
            'approveSubtitle' => __('admin.payment_approval_modal_subtitle'),
            'approveBadge' => __('admin.status_pending'),
            'approveAmountLabel' => __('admin.approval_amount_to_clear'),
            'approveConfirm' => __('admin.confirm_approve_btn'),
            'approveAllTitle' => __('admin.pay_all_approval_modal_title'),
            'approveAllSubtitle' => __('admin.pay_all_approval_modal_subtitle'),
            'approveAllBadge' => __('admin.pay_all_badge'),
            'approveAllAmountLabel' => __('admin.total_settle_amount'),
            'approveAllConfirm' => __('admin.confirm_approve_all_btn'),
            'debtsCount' => __('admin.debts_count'),
            'noDebts' => __('admin.no_debts_found'),
            'statusPaid' => __('admin.status_paid'),
            'settled' => __('admin.settled_badge'),
            'noPayments' => __('admin.debt_detail_no_payments'),
            'noAdjustments' => __('admin.debt_detail_no_adjustments'),
            'remainingLabel' => __('admin.remaining_debt'),
            'afterPaymentLabel' => __('admin.debt_modal_after_payment'),
            'afterAdjustLabel' => __('admin.debt_modal_after_adjust'),
            'payFull' => __('admin.debt_modal_pay_full'),
            'referencePlaceholder' => __('admin.debt_modal_reference_placeholder'),
            'reasonPlaceholder' => __('admin.debt_modal_reason_placeholder'),
            'adjustDecreaseHint' => __('admin.debt_adjust_decrease_hint'),
            'adjustIncreaseHint' => __('admin.debt_adjust_increase_hint'),
            'adjustWaiveHint' => __('admin.debt_adjust_waive_hint'),
            'adjustCorrectionHint' => __('admin.debt_adjust_correction_hint'),
            'invalidAmount' => __('admin.debt_modal_invalid_amount'),
            'reasonRequired' => __('admin.debt_modal_reason_required'),
            'adjustNegative' => __('admin.adjustment_cannot_negative'),
        ];
    @endphp
    <div id="debt-modal" data-i18n="{{ json_encode($debtModalI18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
        <div id="debt-backdrop" class="absolute inset-0"></div>
        {{-- Shell shared by the "Thu tiền" and "Điều chỉnh" forms; resources/js/admin/debts.js fills the header and body. --}}
        <div class="relative z-10 w-full max-w-lg max-h-[92vh] flex flex-col bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="debt-modal-title">
            <div class="flex items-start gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low/60">
                <span id="debt-modal-icon-wrap" class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                    <span class="material-symbols-outlined text-[22px]" id="debt-modal-icon" aria-hidden="true">payments</span>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-base text-on-surface" id="debt-modal-title">{{ __('admin.record_payment') }}</h3>
                    <p class="text-xs text-outline mt-0.5 truncate" id="debt-modal-subtitle"></p>
                </div>
                <button type="button" onclick="closeDebtModal()" class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors" aria-label="{{ __('admin.close_modal_btn') }}">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                </button>
            </div>
            <div id="debt-modal-body" class="overflow-y-auto"></div>
        </div>
    </div>

    <!-- Approve Payment Detail Modal -->
    <div id="approve-debt-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
        <div id="approve-debt-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-lg bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant bg-amber-50/60 shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]" id="approve-modal-icon">pending_actions</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-base text-on-surface" id="approve-modal-title">{{ __('admin.payment_approval_modal_title') }}</h3>
                        <p class="text-[11px] text-outline mt-0.5" id="approve-modal-subtitle">{{ __('admin.payment_approval_modal_subtitle') }}</p>
                    </div>
                </div>
                <button type="button" id="approve-modal-close" class="w-8 h-8 flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-5 space-y-4 overflow-y-auto flex-1 text-xs">
                <!-- User Requester -->
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[18px] text-outline mt-0.5 shrink-0">account_circle</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-0.5">{{ __('admin.requester_user') }}</div>
                        <div class="font-bold text-on-surface text-sm" id="approve-modal-member">—</div>
                        <div class="text-xs text-outline mt-0.5" id="approve-modal-member-meta">—</div>
                    </div>
                    <span id="approve-modal-status-badge"
                          class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-amber-100 text-amber-900 border-amber-300 shrink-0">
                        <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">schedule</span>
                        <span id="approve-modal-badge-text">{{ __('admin.status_pending') }}</span>
                    </span>
                </div>

                <!-- Divider -->
                <div class="border-t border-outline-variant/50"></div>

                <!-- Grid Info (Single debt view) -->
                <div class="grid grid-cols-2 gap-3" id="approve-modal-single-info">
                    <!-- Request time -->
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_time') }}</div>
                        <div class="text-xs font-semibold text-on-surface" id="approve-modal-time">—</div>
                    </div>
                    <!-- Campaign -->
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.origin_campaign') }}</div>
                        <div class="text-xs font-semibold text-on-surface truncate" id="approve-modal-campaign">—</div>
                    </div>
                </div>

                <!-- Transfer content / Note -->
                <div class="bg-surface-container rounded-lg p-3">
                    <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_content') }}</div>
                    <div class="text-xs font-mono text-primary font-bold tracking-wide" id="approve-modal-content">—</div>
                </div>

                <!-- Debts Breakdown Container (Shown when isPayAll) -->
                <div id="approve-modal-breakdown-container" class="hidden space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-mono uppercase text-outline tracking-wider">
                        <span>{{ __('admin.debts_breakdown_title') }}</span>
                        <span id="approve-modal-breakdown-count" class="font-bold text-primary"></span>
                    </div>
                    <div class="border border-outline-variant/60 rounded-xl overflow-hidden divide-y divide-outline-variant/40 max-h-48 overflow-y-auto bg-surface-container-low" id="approve-modal-breakdown-list">
                    </div>
                </div>

                <!-- Amount to clear -->
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-mono uppercase text-emerald-700 tracking-wider mb-0.5" id="approve-modal-amount-label">{{ __('admin.approval_amount_to_clear') }}</div>
                        <div class="text-2xl font-bold font-mono text-emerald-700" id="approve-modal-amount">—</div>
                    </div>
                    <span class="material-symbols-outlined text-[32px] text-emerald-400">payments</span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-outline-variant flex items-center justify-end gap-3 bg-surface-container-low/40 shrink-0">
                <button type="button" id="approve-modal-cancel"
                        class="px-4 py-2 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg text-xs font-semibold transition-colors cursor-pointer">
                    {{ __('admin.cancel_btn') }}
                </button>
                <button type="button" id="approve-modal-confirm"
                        data-debt-id=""
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">check_circle</span>
                    <span id="approve-modal-confirm-text">{{ __('admin.confirm_approve_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Consolidated Payment Request Review Modal -->
    @php
        $paymentRequestI18n = [
            'conflict' => __('admin.payment_request_conflict_notice'),
            'reviewed' => __('admin.payment_request_reviewed'),
            'reason' => __('room.debts.request_reason'),
            'reasonRequired' => __('admin.debt_modal_reason_required'),
            'serverError' => __('admin.debt_modal_server_error'),
        ];
    @endphp
    <div id="payment-request-modal" data-i18n="{{ json_encode($paymentRequestI18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
        data-approve-url="{{ route('admin.debts.payment-requests.approve', [$room, '__ID__']) }}"
        data-reject-url="{{ route('admin.debts.payment-requests.reject', [$room, '__ID__']) }}"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="payment-request-title">
        <div class="absolute inset-0" data-payment-request-close></div>
        <div class="relative z-10 w-full max-w-lg bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant bg-amber-50/60 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">stacks</span>
                    </span>
                    <div class="min-w-0">
                        <h3 id="payment-request-title" class="font-bold text-base text-on-surface">{{ __('admin.payment_requests_title') }}</h3>
                        <p class="text-[11px] font-mono text-outline mt-0.5" data-payment-request-field="code">—</p>
                    </div>
                </div>
                <button type="button" data-payment-request-close aria-label="{{ __('admin.close') }}" class="w-8 h-8 flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4 overflow-y-auto flex-1 text-xs">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[18px] text-outline mt-0.5 shrink-0" aria-hidden="true">account_circle</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-0.5">{{ __('admin.requester_user') }}</div>
                        <div class="font-bold text-on-surface text-sm" data-payment-request-field="member">—</div>
                        <div class="text-xs text-outline mt-0.5" data-payment-request-field="member_meta">—</div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-surface-container text-on-surface border-outline-variant shrink-0" data-payment-request-field="status_label">—</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_time') }}</div>
                        <div class="text-xs font-semibold text-on-surface" data-payment-request-field="requested_at">—</div>
                    </div>
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_content') }}</div>
                        <div class="text-xs font-mono font-bold text-primary break-words" data-payment-request-field="transfer_content">—</div>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-mono uppercase text-outline tracking-wider">
                        <span>{{ __('admin.payment_request_frozen_debts') }}</span>
                        <span class="font-bold text-primary" data-payment-request-field="debt_count"></span>
                    </div>
                    {{-- No inner scroll: the modal body already scrolls. --}}
                    <div class="border border-outline-variant/60 rounded-xl overflow-hidden divide-y divide-outline-variant/40 bg-surface-container-low" data-payment-request-debts></div>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-mono uppercase text-emerald-700 tracking-wider mb-0.5">{{ __('admin.payment_request_amount') }}</div>
                        <div class="text-2xl font-bold font-mono text-emerald-700" data-payment-request-field="amount">—</div>
                    </div>
                    <span class="material-symbols-outlined text-[32px] text-emerald-400" aria-hidden="true">payments</span>
                </div>

                <p class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700" data-payment-request-conflict role="alert"></p>
                <p class="hidden rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-on-surface" data-payment-request-review></p>

                <div class="hidden space-y-1.5" data-payment-request-reject-form>
                    <label for="payment-request-reason" class="block font-semibold text-on-surface">{{ __('admin.debt_modal_reason') }} <span class="text-error">*</span></label>
                    <textarea id="payment-request-reason" rows="2" maxlength="500" placeholder="{{ __('admin.payment_request_reject_placeholder') }}" class="w-full p-3 bg-surface border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors"></textarea>
                </div>
                <p class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700" data-payment-request-error role="alert"></p>
            </div>

            <div class="px-6 py-4 border-t border-outline-variant flex flex-wrap items-center justify-end gap-2 bg-surface-container-low/40 shrink-0">
                <button type="button" data-payment-request-close class="px-4 py-2 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg text-xs font-semibold transition-colors cursor-pointer">{{ __('admin.close') }}</button>
                <button type="button" data-payment-request-action="reject" class="hidden px-4 py-2 border border-rose-300 text-rose-700 hover:bg-rose-50 rounded-lg text-xs font-bold items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true" data-action-icon="block">block</span>
                    <span data-reject-label data-label-start="{{ __('admin.payment_request_reject_btn') }}" data-label-confirm="{{ __('admin.payment_request_reject_confirm') }}">{{ __('admin.payment_request_reject_btn') }}</span>
                </button>
                <button type="button" data-payment-request-action="approve" class="hidden px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold items-center gap-1.5 shadow-xs transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true" data-action-icon="check_circle">check_circle</span>
                    <span>{{ __('admin.payment_request_approve_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Debt Detail Modal (read-only) -->
    <div id="debt-detail-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="debt-detail-title">
        <div id="debt-detail-backdrop" class="absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-2xl bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant bg-surface-container-low shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    </span>
                    <div>
                        <h3 id="debt-detail-title" class="font-bold text-base text-on-surface">{{ __('admin.debt_detail') }}</h3>
                        <p class="text-[11px] font-mono text-outline mt-0.5" data-debt-detail-field="code">—</p>
                    </div>
                </div>
                <button type="button" data-debt-detail-close aria-label="{{ __('admin.close') }}" class="w-8 h-8 flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="px-6 pt-3 border-b border-outline-variant shrink-0 flex items-center gap-1 text-xs font-semibold" role="tablist" aria-label="{{ __('admin.debt_detail') }}">
                <button type="button" role="tab" data-debt-detail-tab="basic" aria-selected="true" aria-controls="debt-detail-panel-basic"
                    class="px-3 py-2 -mb-px border-b-2 border-primary text-primary transition-colors cursor-pointer">
                    {{ __('admin.debt_detail_tab_basic') }}
                </button>
                <button type="button" role="tab" data-debt-detail-tab="history" aria-selected="false" aria-controls="debt-detail-panel-history"
                    class="px-3 py-2 -mb-px border-b-2 border-transparent text-outline hover:text-on-surface transition-colors cursor-pointer inline-flex items-center gap-1.5">
                    <span>{{ __('admin.debt_detail_tab_history') }}</span>
                    <span class="px-1.5 py-0.5 rounded-full bg-surface-container text-[10px] font-mono text-outline" data-debt-detail-history-count>0</span>
                </button>
            </div>

            <div id="debt-detail-panel-basic" role="tabpanel" data-debt-detail-panel="basic" class="px-6 py-5 space-y-4 overflow-y-auto flex-1 text-xs">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[18px] text-outline mt-0.5 shrink-0">account_circle</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-0.5">{{ __('admin.debt_member') }}</div>
                        <div class="font-bold text-on-surface text-sm" data-debt-detail-field="member">—</div>
                        <div class="text-xs text-outline mt-0.5" data-debt-detail-field="member_meta">—</div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-surface-container text-on-surface border-outline-variant shrink-0" data-debt-detail-field="status_label">—</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.origin_campaign') }}</div>
                        <div class="text-xs font-semibold text-on-surface break-words" data-debt-detail-field="campaign">—</div>
                    </div>
                    <div class="bg-surface-container rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.created_at') }}</div>
                        <div class="text-xs font-semibold text-on-surface" data-debt-detail-field="created_at">—</div>
                    </div>
                    <div class="bg-surface-container rounded-lg p-3" data-debt-detail-row="payment_requested_at">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_time') }}</div>
                        <div class="text-xs font-semibold text-on-surface" data-debt-detail-field="payment_requested_at">—</div>
                    </div>
                    <div class="bg-surface-container rounded-lg p-3" data-debt-detail-row="payment_content">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.request_content') }}</div>
                        <div class="text-xs font-mono font-bold text-primary break-words" data-debt-detail-field="payment_content">—</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="border border-outline-variant/60 rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.th_original_debt') }}</div>
                        <div class="font-mono font-bold text-on-surface" data-debt-detail-field="original">—</div>
                    </div>
                    <div class="border border-outline-variant/60 rounded-lg p-3" data-debt-detail-row="sponsor">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.debt_detail_sponsor_amount') }}</div>
                        <div class="font-mono font-bold text-emerald-700" data-debt-detail-field="sponsor">—</div>
                        <div class="text-[11px] text-outline mt-0.5" data-debt-detail-field="sponsor_type"></div>
                    </div>
                    <div class="border border-outline-variant/60 rounded-lg p-3" data-debt-detail-row="adjustment">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.debt_detail_adjustment_amount') }}</div>
                        <div class="font-mono font-bold text-on-surface" data-debt-detail-field="adjustment">—</div>
                    </div>
                    <div class="border border-outline-variant/60 rounded-lg p-3">
                        <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.debt_detail_paid_amount') }}</div>
                        <div class="font-mono font-bold text-emerald-700" data-debt-detail-field="paid">—</div>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 col-span-2 sm:col-span-1">
                        <div class="text-[11px] font-mono uppercase text-amber-700 tracking-wider mb-1">{{ __('admin.remaining_debt') }}</div>
                        <div class="font-mono font-bold text-amber-700 text-base" data-debt-detail-field="remaining">—</div>
                    </div>
                </div>

                <div class="bg-surface-container rounded-lg p-3" data-debt-detail-row="note">
                    <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.notes') }}</div>
                    <div class="text-xs text-on-surface break-words whitespace-pre-line" data-debt-detail-field="note">—</div>
                </div>
                <div class="bg-surface-container rounded-lg p-3" data-debt-detail-row="sponsor_description">
                    <div class="text-[11px] font-mono uppercase text-outline tracking-wider mb-1">{{ __('admin.sponsor_description_label') }}</div>
                    <div class="text-xs text-on-surface break-words whitespace-pre-line" data-debt-detail-field="sponsor_description">—</div>
                </div>
            </div>

            <div id="debt-detail-panel-history" role="tabpanel" data-debt-detail-panel="history" class="hidden px-6 py-5 space-y-4 overflow-y-auto flex-1 text-xs">
                <div class="space-y-2">
                    <div class="text-[11px] font-mono uppercase text-outline tracking-wider">{{ __('admin.debt_detail_payment_history') }}</div>
                    <div class="border border-outline-variant/60 rounded-xl overflow-hidden divide-y divide-outline-variant/40" data-debt-detail-list="payments"></div>
                </div>
                <div class="space-y-2">
                    <div class="text-[11px] font-mono uppercase text-outline tracking-wider">{{ __('admin.debt_detail_adjustment_history') }}</div>
                    <div class="border border-outline-variant/60 rounded-xl overflow-hidden divide-y divide-outline-variant/40" data-debt-detail-list="adjustments"></div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-outline-variant flex items-center justify-end bg-surface-container-low/40 shrink-0">
                <button type="button" data-debt-detail-close class="px-4 py-2 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg text-xs font-semibold transition-colors cursor-pointer">{{ __('admin.close') }}</button>
            </div>
        </div>
    </div>
</x-admin.layout>
