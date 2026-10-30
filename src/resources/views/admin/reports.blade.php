<x-admin.layout :title="__('admin.reports_analytics_title')" active="reports" :room="$room">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.reports_analytics_title') }}</h1>
        </div>
        <div @class(['flex items-center gap-3', 'hidden' => $activeReportTab === 'badges']) data-report-range-actions>
            <x-admin.date-range-filter id="report-date-range" :date-from="$dateFrom" :date-to="$dateTo" />
            <button type="button" onclick="exportReportCSV()" class="px-3.5 py-2 bg-surface-container hover:bg-surface-container-high border border-outline-variant text-on-surface rounded text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors">
                <span class="material-symbols-outlined text-[16px] text-primary">download</span>
                <span>{{ __('admin.export_excel_csv') }}</span>
            </button>
        </div>
    </div>

    @php
        $reportI18n = [
            'member' => __('admin.report_col_member'),
            'debtCount' => __('admin.report_col_debt_count'),
            'totalOriginal' => __('admin.total_debt_amount'),
            'totalPaid' => __('admin.total_debt_paid'),
            'totalRemaining' => __('admin.total_debt_remaining'),
            'status' => __('admin.report_col_status'),
            'statusCleared' => __('admin.report_status_cleared'),
            'statusOwing' => __('admin.report_status_owing'),
            'rank' => __('admin.report_col_rank'),
            'sponsor' => __('admin.report_col_sponsor'),
            'sponsoredOrders' => __('admin.report_col_sponsored_orders'),
            'totalSponsored' => __('admin.report_col_total_sponsored'),
            'ordersPlaced' => __('admin.th_orders_placed_count'),
            'totalSpent' => __('admin.report_col_total_spent'),
            'noDrinksTitle' => __('admin.report_no_drinks_found'),
            'noDrinksDesc' => __('admin.report_no_drinks_found_desc'),
            'noStoresTitle' => __('admin.report_no_stores_found'),
            'noStoresDesc' => __('admin.report_no_stores_found_desc'),
            'noDebtsTitle' => __('admin.report_no_debts_found'),
            'noDebtsDesc' => __('admin.report_no_debts_found_desc'),
            'noSponsorsTitle' => __('admin.no_sponsors_found'),
            'noSponsorsDesc' => __('admin.no_sponsors_found_desc'),
            'noUsersTitle' => __('admin.no_users_analytics_found'),
            'noUsersDesc' => __('admin.no_users_analytics_found_desc'),
            'copyFailed' => __('admin.copy_failed'),
            'copyEmail' => __('admin.copy_email'),
            'paymentAccount' => __('admin.report_col_payment_account'),
            'ordersCount' => __('admin.th_orders_count'),
            'totalReceived' => __('admin.report_col_total_received'),
            'totalOutstanding' => __('admin.report_col_total_outstanding'),
            'accountUnassigned' => __('admin.report_payment_account_unassigned'),
            'toggleAccountNumber' => __('admin.toggle_account_number_visibility'),
            'noAccountsTitle' => __('admin.report_no_payment_accounts_found'),
            'noAccountsDesc' => __('admin.report_no_payment_accounts_found_desc'),
        ];
    @endphp
    <!-- 5 Report Tabs Navigation -->
    <div id="report-tabs" data-i18n="{{ json_encode($reportI18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" class="flex items-center gap-2 border-b border-outline-variant overflow-x-auto text-xs font-semibold">
        <button type="button" onclick="switchReportTab('campaigns')" id="rtab-campaigns" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-primary text-primary font-bold transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">analytics</span>
            <span>{{ __('admin.tab_campaigns_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('products')" id="rtab-products" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">local_cafe</span>
            <span>{{ __('admin.tab_products_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('debts')" id="rtab-debts" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
            <span>{{ __('admin.tab_debts_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('sponsors')" id="rtab-sponsors" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">volunteer_activism</span>
            <span>{{ __('admin.tab_sponsors_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('accounts')" id="rtab-accounts" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">account_balance</span>
            <span>{{ __('admin.tab_payment_accounts_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('users')" id="rtab-users" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">group</span>
            <span>{{ __('admin.tab_users_analytics') }}</span>
        </button>
        <button type="button" onclick="switchReportTab('badges')" id="rtab-badges" class="rtab flex items-center gap-2 px-3.5 py-2.5 border-b-2 border-transparent text-outline hover:text-on-surface transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
            <span>{{ __('sponsor_honors.report_tab') }}</span>
        </button>
    </div>

    <!-- 4 Dynamic KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.kpi_total_campaigns') }}</span>
                <span class="material-symbols-outlined text-[20px] text-primary">campaign</span>
            </div>
            <div class="text-2xl font-bold font-mono text-on-surface mt-2" id="kpi-campaigns">{{ $stats['campaign_count'] ?? 0 }}</div>
            <div class="text-[11px] text-emerald-700 mt-1">{{ __('admin.selected_period_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.kpi_total_orders_placed') }}</span>
                <span class="material-symbols-outlined text-[20px] text-secondary">shopping_cart_checkout</span>
            </div>
            <div class="text-2xl font-bold font-mono text-on-surface mt-2" id="kpi-orders">{{ $stats['order_count'] ?? 0 }}</div>
            <div class="text-[11px] text-outline mt-1">{{ __('admin.on_time_completed_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.kpi_total_store_spending') }}</span>
                <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
            </div>
            <div class="text-2xl font-bold font-mono text-primary mt-2" id="kpi-spending">{{ \App\Support\Helpers\FormatHelper::formatCurrency((float) ($stats['spending'] ?? 0)) }}</div>
            <div class="text-[11px] text-outline mt-1">{{ __('admin.actual_invoice_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.kpi_sponsor_fund_subsidies') }}</span>
                <span class="material-symbols-outlined text-[20px] text-emerald-600">savings</span>
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 mt-2" id="kpi-sponsor">{{ \App\Support\Helpers\FormatHelper::formatCurrency((float) ($stats['sponsor_amount'] ?? 0)) }}</div>
            <div class="text-[11px] text-emerald-700 mt-1">{{ __('admin.sponsor_for_members_desc') }}</div>
        </div>
    </div>

    <!-- Tab 1: Campaign Analytics Panel -->
    <div id="panel-campaigns" class="report-panel space-y-6">
        <!-- Visual Participation Ratio Component -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between pb-3 border-b border-outline-variant gap-2">
                <div>
                    <h2 class="font-bold text-sm text-on-surface">{{ __('admin.participation_ratio_heading') }}</h2>
                    <p class="text-[11px] text-outline">{{ __('admin.total_room_members_label') }} {{ $roomMembersCount ?? 0 }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] font-semibold">
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> {{ __('admin.participation_low') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> {{ __('admin.participation_medium') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> {{ __('admin.participation_high') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-surface-container-high inline-block"></span> {{ __('admin.not_participating') }}</span>
                </div>
            </div>

            <div class="mt-4 space-y-3.5 text-xs">
                @forelse($campaigns->take(5) as $c)
                    @php
                        $ordersCount = (int) $c->orders_count;
                        $pct = $roomMembersCount > 0 ? min(100, round(($ordersCount / $roomMembersCount) * 100, 1)) : 0;
                        // Participation bands: < 50% low (rose), < 80% medium (amber), otherwise high (emerald).
                        $band = $pct < 50 ? 'low' : ($pct < 80 ? 'medium' : 'high');
                        [$barClass, $pctClass] = match ($band) {
                            'low' => ['bg-rose-500', 'text-rose-600 dark:text-rose-400'],
                            'medium' => ['bg-amber-500', 'text-amber-600 dark:text-amber-400'],
                            default => ['bg-emerald-500', 'text-emerald-600 dark:text-emerald-400'],
                        };
                    @endphp
                    <div data-participation-band="{{ $band }}">
                        <div class="flex justify-between items-center mb-1 text-on-surface">
                            <span class="font-bold text-xs">{{ $c->name ?? $c->title }} ({{ $c->restaurant }})</span>
                            <span class="font-mono text-outline">{{ $ordersCount }} (<span class="font-bold {{ $pctClass }}">{{ $pct }}%</span>)</span>
                        </div>
                        <div class="w-full h-2.5 bg-surface-container-high rounded-full flex overflow-hidden">
                            <div class="{{ $barClass }} rounded-full hover:opacity-90 transition-all" style="width: {{ $pct }}%" title="{{ $ordersCount }}"></div>
                        </div>
                    </div>
                @empty
                    <x-admin.empty-state icon="campaign" :title="__('admin.no_campaigns_analytics_found')" :description="__('admin.no_campaigns_analytics_found_desc')" />
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab 2: Product Analytics Panel -->
    <div id="panel-products" class="report-panel space-y-6 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs">
                <h3 class="font-bold text-sm text-on-surface mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">local_cafe</span>
                    <span>{{ __('admin.top_5_drinks') }}</span>
                </h3>
                <div id="top-drinks-list" class="space-y-2.5 text-xs">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs">
                <h3 class="font-bold text-sm text-on-surface mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">storefront</span>
                    <span>{{ __('admin.top_5_stores') }}</span>
                </h3>
                <div id="top-stores-list" class="space-y-2.5 text-xs">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Debt Analytics Panel (Công nợ theo từng User) -->
    <div id="panel-debts" class="report-panel space-y-6 hidden">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-outline-variant">
                <div>
                    <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">account_balance_wallet</span>
                        <span>{{ __('admin.debts_analytics_title') }}</span>
                    </h3>
                    <p class="text-[11px] text-outline mt-0.5">{{ __('admin.debts_analytics_desc') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-container-low rounded-lg border border-outline-variant text-xs">
                        <span class="text-outline">{{ __('admin.total_debt_remaining') }}:</span>
                        <span class="font-mono font-bold text-error" id="debt-summary-remaining">{{ \App\Support\Helpers\FormatHelper::formatCurrency((float) ($stats['debt'] ?? 0)) }}</span>
                    </div>
                </div>
            </div>

            <div id="debts-users-list">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Tab 4: Sponsor Analytics Panel -->
    <div id="panel-sponsors" class="report-panel space-y-6 hidden">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-outline-variant">
                <div>
                    <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">volunteer_activism</span>
                        <span>{{ __('admin.sponsor_leaderboard') }}</span>
                    </h3>
                    <p class="text-[11px] text-outline mt-0.5">{{ __('admin.multi_sponsor_title') }}</p>
                </div>
                <div class="px-3 py-1 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 rounded-lg text-xs font-mono font-bold" id="sponsor-subtotal-text">
                    {{ \App\Support\Helpers\FormatHelper::formatCurrency((float) ($stats['sponsor_amount'] ?? 0)) }}
                </div>
            </div>
            
            <div id="sponsors-leaderboard-list">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Tab: Receiving Payment Account Analytics Panel -->
    <div id="panel-accounts" class="report-panel space-y-6 hidden">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs space-y-4">
            <div class="pb-3 border-b border-outline-variant">
                <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">account_balance</span>
                    <span>{{ __('admin.payment_accounts_report_title') }}</span>
                </h3>
                <p class="text-[11px] text-outline mt-0.5">{{ __('admin.payment_accounts_report_desc') }}</p>
            </div>

            <div id="payment-accounts-list">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Tab 5: User Analytics Panel -->
    <div id="panel-users" class="report-panel space-y-6 hidden">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs space-y-4">
            <div class="pb-3 border-b border-outline-variant">
                <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">group</span>
                    <span>{{ __('admin.user_reliability') }}</span>
                </h3>
                <p class="text-[11px] text-outline mt-0.5">{{ __('admin.users_directory') }}</p>
            </div>

            <div id="users-analytics-list">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Tab: Monthly sponsor badges (server-rendered, filtered by year rather than the date range) -->
    @php
        $badgeTones = [1 => 'bg-amber-100 text-amber-600 ring-amber-300', 2 => 'bg-slate-100 text-slate-500 ring-slate-300', 3 => 'bg-orange-100 text-orange-700 ring-orange-300'];
        $badgeMetals = [1 => 'gold', 2 => 'silver', 3 => 'bronze'];
    @endphp
    <div id="panel-badges" class="report-panel space-y-6 hidden" data-badge-report>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-outline-variant">
                <div>
                    <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">workspace_premium</span>
                        <span>{{ __('sponsor_honors.report_title', ['year' => $badgeReport['year']]) }}</span>
                    </h3>
                    <p class="text-[11px] text-outline mt-0.5">{{ __('sponsor_honors.report_desc') }}</p>
                </div>
                <form method="GET" action="{{ route('admin.reports.page', $room) }}" class="flex items-center gap-2" data-no-loading>
                    <input type="hidden" name="report_tab" value="badges">
                    <label for="badge-year" class="text-xs font-semibold text-outline">{{ __('sponsor_honors.report_year') }}</label>
                    <select id="badge-year" name="badge_year" onchange="this.form.submit()" class="rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs text-on-surface">
                        @foreach ($badgeReport['years'] as $year)
                            <option value="{{ $year }}" @selected($year === $badgeReport['year'])>{{ $year }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="text-xs font-semibold text-primary">{{ __('sponsor_honors.report_apply') }}</button></noscript>
                </form>
            </div>

            @if (count($badgeReport['months']) === 0)
                <div class="flex flex-col items-center justify-center py-10 px-4 text-center rounded-xl border border-dashed border-outline-variant/80 bg-surface-container-low/30">
                    <span class="material-symbols-outlined text-[26px] text-outline mb-2" aria-hidden="true">workspace_premium</span>
                    <p class="text-xs text-outline font-medium">{{ __('sponsor_honors.report_empty', ['year' => $badgeReport['year']]) }}</p>
                </div>
            @else
                {{-- Monthly badges, then the per-member totals below (one table per row). --}}
                <div class="grid grid-cols-1 gap-4">
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-on-surface">{{ __('sponsor_honors.report_by_month') }}</h4>
                        <div class="overflow-x-auto border border-outline-variant/60 rounded-xl">
                            <table class="w-full text-left text-xs border-collapse" data-badge-months>
                                <thead>
                                    <tr class="bg-surface-container-low text-outline uppercase text-[11px] tracking-wide font-semibold border-b border-outline-variant/60">
                                        <th class="py-3 px-4">{{ __('sponsor_honors.report_col_month') }}</th>
                                        @foreach ($badgeMetals as $rank => $metal)
                                            <th class="py-3 px-4">{{ __('sponsor_honors.history_rank', ['rank' => $rank]) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/40">
                                    @foreach ($badgeReport['months'] as $monthRow)
                                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                                            <td class="py-3 px-4 font-mono font-semibold text-on-surface whitespace-nowrap">{{ $monthRow['month'] }}</td>
                                            @foreach ($badgeMetals as $rank => $metal)
                                                @php
                                                    $winner = $monthRow['podium'][$rank - 1] ?? null;
                                                @endphp
                                                <td class="py-3 px-4">
                                                    @if ($winner)
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <x-sponsor-badge :rank="$rank" :month="$monthRow['month']" />
                                                            <span class="truncate font-medium text-on-surface">{{ $winner['name'] }}</span>
                                                        </div>
                                                        <div class="text-[11px] text-outline font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency((float) $winner['amount']) }}</div>
                                                    @else
                                                        <span class="text-outline">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-on-surface">{{ __('sponsor_honors.report_by_member', ['year' => $badgeReport['year']]) }}</h4>
                        <div class="overflow-x-auto border border-outline-variant/60 rounded-xl">
                            <table class="w-full text-left text-xs border-collapse" data-badge-members>
                                <thead>
                                    <tr class="bg-surface-container-low text-outline uppercase text-[11px] tracking-wide font-semibold border-b border-outline-variant/60">
                                        <th class="py-3 px-4">{{ __('admin.report_col_member') }}</th>
                                        @foreach ($badgeMetals as $rank => $metal)
                                            <th class="py-3 px-4 text-center">{{ __('sponsor_honors.'.$metal) }}</th>
                                        @endforeach
                                        <th class="py-3 px-4 text-center">{{ __('sponsor_honors.report_col_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/40">
                                    @foreach ($badgeReport['members'] as $member)
                                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                                            <td class="py-3 px-4 font-semibold text-on-surface">{{ $member['name'] }}</td>
                                            @foreach ($badgeMetals as $rank => $metal)
                                                <td class="py-3 px-4 text-center">
                                                    @if ($member[$metal] > 0)
                                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 ring-1 font-bold {{ $badgeTones[$rank] }}">
                                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">workspace_premium</span>{{ $member[$metal] }}
                                                        </span>
                                                    @else
                                                        <span class="text-outline">0</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="py-3 px-4 text-center font-bold text-primary font-mono">{{ $member['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if ($activeReportTab === 'badges')
        {{-- Runs after the admin.js module has registered switchReportTab. --}}
        <script>window.addEventListener('load', () => window.switchReportTab?.('badges'));</script>
    @endif
</x-admin.layout>
