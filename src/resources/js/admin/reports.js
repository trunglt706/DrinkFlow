import { formatMoney } from '../shared/money';

/**
 * Admin Reports & Financial Analytics Controller
 */
export function initAdminReports() {
    const dateRangePicker = document.querySelector('#report-date-range');
    const roomSlug = document.querySelector('[data-room-slug]')?.dataset.roomSlug || window.__DF_ROOM_SLUG__ || '';
    const money = formatMoney;

    let currentActiveTab = 'campaigns';
    const loadedTabCache = new Set();

    function emptyStateHtml(icon = 'inbox', title = '', description = '') {
        return `
            <div class="flex flex-col items-center justify-center py-10 px-4 text-center rounded-xl border border-dashed border-outline-variant/80 bg-surface-container-low/30">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high/60 text-outline mb-2.5">
                    <span class="material-symbols-outlined text-[26px]">${icon}</span>
                </div>
                ${title ? `<h4 class="text-xs font-bold text-on-surface mb-0.5">${title}</h4>` : ''}
                <p class="text-xs text-outline font-medium max-w-sm leading-relaxed">${description}</p>
            </div>
        `;
    }

    const i18n = JSON.parse(document.querySelector('#report-tabs')?.dataset.i18n || '{}');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);

    // Gold / silver / bronze medals for the top 3; ranks 4+ keep a plain number.
    const MEDAL_TONES = [
        'bg-amber-100 text-amber-500 ring-1 ring-amber-300 dark:bg-amber-950/50 dark:ring-amber-700',
        'bg-slate-100 text-slate-400 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600',
        'bg-orange-100 text-orange-700 ring-1 ring-orange-300 dark:bg-orange-950/50 dark:text-orange-400 dark:ring-orange-800',
    ];
    /**
     * Rank marker for the top drinks / top stores lists.
     *
     * @param {number} idx Zero-based position in the list.
     * @returns {string} HTML of the medal icon (top 3) or the rank number.
     */
    const rankBadge = (idx) => {
        const label = escapeHtml(`${i18n.rank || ''} ${idx + 1}`.trim());
        if (idx < MEDAL_TONES.length) {
            return `<span data-rank-medal="${idx + 1}" title="${label}" aria-label="${label}" class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center ${MEDAL_TONES[idx]}"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">military_tech</span></span>`;
        }

        return `<span title="${label}" class="w-6 h-6 shrink-0 rounded-full bg-surface-container-high text-outline font-bold flex items-center justify-center text-[10px]">${idx + 1}</span>`;
    };
    const ALIGN_CLASS = { left: 'text-left', center: 'text-center', right: 'text-right' };

    /**
     * Render the member identity cell: display name with the account email (and a copy button) underneath.
     * Copying is handled by the shared [data-copy] helper (ui-enhancements.js): the icon turns into a check mark
     * for 2 seconds instead of showing a toast.
     */
    function memberCellHtml(name, email) {
        const copyButton = email ? `
            <button type="button" data-copy="${escapeHtml(email)}" data-copy-feedback="icon"
                data-copy-failed-message="${escapeHtml(i18n.copyFailed || '')}"
                data-tooltip="${escapeHtml(i18n.copyEmail || '')}"
                aria-label="${escapeHtml(i18n.copyEmail || '')}"
                class="inline-flex shrink-0 items-center text-outline hover:text-primary transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">content_copy</span>
            </button>` : '';

        return `
            <div class="font-semibold text-on-surface truncate">${escapeHtml(name || i18n.member)}</div>
            ${email ? `<div class="flex items-center gap-1 min-w-0 text-[11px] text-outline"><span class="truncate">${escapeHtml(email)}</span>${copyButton}</div>` : ''}
        `;
    }

    /**
     * Render a report table whose header and body share one column definition,
     * so widths, alignment and typography always match.
     *
     * @param {Array<{label: string, align?: string, width?: string}>} columns Column definitions.
     * @param {Array<Array<string>>} rows Pre-rendered cell HTML per row, in column order.
     * @param {{minWidth?: string}} [options] minWidth overrides the default minimum table width (it scrolls sideways below it).
     */
    function reportTableHtml(columns, rows, options = {}) {
        const cellClass = (column) => `py-3 px-4 align-middle ${ALIGN_CLASS[column.align || 'left']}`;
        return `
            <div class="overflow-x-auto border border-outline-variant/60 rounded-xl">
                <table class="report-table table-colgroup w-full table-fixed text-left text-xs border-collapse"${options.minWidth ? ` style="min-width:${options.minWidth}"` : ''}>
                    <colgroup>${columns.map((column) => `<col${column.width ? ` style="width:${column.width}"` : ''}>`).join('')}</colgroup>
                    <thead>
                        <tr class="bg-surface-container-low text-outline uppercase text-[11px] tracking-wide font-semibold border-b border-outline-variant/60">
                            ${columns.map((column) => `<th class="${cellClass(column)}">${escapeHtml(column.label)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        ${rows.map((cells) => `
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                ${cells.map((cell, index) => `<td class="${cellClass(columns[index])}">${cell}</td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    if (!dateRangePicker && !document.querySelector('.rtab')) return;

    // Show the full account number or the masked one again (rows are re-rendered, so the listener is delegated).
    document.querySelector('#payment-accounts-list')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-account-number-toggle]');
        const number = button?.parentElement.querySelector('[data-account-number]');
        if (!number) return;
        const revealed = button.getAttribute('aria-pressed') !== 'true';
        number.textContent = revealed ? number.dataset.full : number.dataset.masked;
        button.setAttribute('aria-pressed', String(revealed));
        button.querySelector('.material-symbols-outlined').textContent = revealed ? 'visibility_off' : 'visibility';
    });

    window.switchReportTab = function(tabId) {
        currentActiveTab = tabId;

        document.querySelectorAll('.report-panel').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('.rtab').forEach(b => {
            b.classList.remove('border-primary', 'text-primary', 'font-bold');
            b.classList.add('border-transparent', 'text-outline');
        });

        document.querySelector(`#panel-${tabId}`)?.classList.remove('hidden');
        const activeBtn = document.querySelector(`#rtab-${tabId}`);
        activeBtn?.classList.add('border-primary', 'text-primary', 'font-bold');
        activeBtn?.classList.remove('border-transparent', 'text-outline');

        // The badges tab is filtered by year, so the date range picker and export do not apply to it.
        document.querySelector('[data-report-range-actions]')?.classList.toggle('hidden', tabId === 'badges');

        // Lazy load tab data on active (the badges tab is server-rendered and filtered by year).
        if (tabId !== 'campaigns' && tabId !== 'badges') {
            window.loadTabReportData(tabId);
        }
    };

    window.loadTabReportData = async function(tabId = currentActiveTab, force = false) {
        if (tabId === 'badges') return;
        const dateFrom = dateRangePicker?.querySelector('.date-from-hidden')?.value || '';
        const dateTo = dateRangePicker?.querySelector('.date-to-hidden')?.value || '';
        const cacheKey = `${tabId}_${dateFrom}_${dateTo}`;

        if (!force && loadedTabCache.has(cacheKey)) {
            return;
        }

        const drinksList = document.querySelector('#top-drinks-list');
        const storesList = document.querySelector('#top-stores-list');
        const debtsList = document.querySelector('#debts-users-list');
        const sponsorsList = document.querySelector('#sponsors-leaderboard-list');
        const usersList = document.querySelector('#users-analytics-list');
        const accountsList = document.querySelector('#payment-accounts-list');

        const skeletonHtml = `
            <div class="space-y-2 animate-pulse py-2">
                <div class="h-10 bg-slate-200 dark:bg-slate-800 rounded"></div>
                <div class="h-10 bg-slate-200 dark:bg-slate-800 rounded"></div>
                <div class="h-10 bg-slate-200 dark:bg-slate-800 rounded"></div>
            </div>
        `;

        // Only show skeleton for the currently requested tab
        if (tabId === 'products') {
            if (drinksList) drinksList.innerHTML = skeletonHtml;
            if (storesList) storesList.innerHTML = skeletonHtml;
        } else if (tabId === 'debts') {
            if (debtsList) debtsList.innerHTML = skeletonHtml;
        } else if (tabId === 'sponsors') {
            if (sponsorsList) sponsorsList.innerHTML = skeletonHtml;
        } else if (tabId === 'users') {
            if (usersList) usersList.innerHTML = skeletonHtml;
        } else if (tabId === 'accounts') {
            if (accountsList) accountsList.innerHTML = skeletonHtml;
        }

        try {
            const queryParams = new URLSearchParams();
            if (tabId) queryParams.set('tab', tabId);
            if (dateFrom) queryParams.set('date_from', dateFrom);
            if (dateTo) queryParams.set('date_to', dateTo);
            // An empty range is the "Tất cả" preset; without this the server would fall back to its 7-day default.
            if (!dateFrom && !dateTo) queryParams.set('period', 'all');
            const queryString = queryParams.toString() ? `?${queryParams.toString()}` : '';

            const res = await fetch(`/admin/${roomSlug}/reports${queryString}`, {
                headers: { 'Accept': 'application/json' }
            });
            const { data } = await res.json();
            if (!data) return;

            // Update stats cards
            const kpiCmp = document.querySelector('#kpi-campaigns');
            const kpiOrd = document.querySelector('#kpi-orders');
            const kpiSpd = document.querySelector('#kpi-spending');
            const kpiSpon = document.querySelector('#kpi-sponsor');
            const sponSub = document.querySelector('#sponsor-subtotal-text');
            const debtRemaining = document.querySelector('#debt-summary-remaining');

            if (kpiCmp && data.campaign_count !== undefined) kpiCmp.textContent = data.campaign_count;
            if (kpiOrd && data.order_count !== undefined) kpiOrd.textContent = data.order_count;
            if (kpiSpd && data.spending !== undefined) kpiSpd.textContent = money(data.spending);
            if (kpiSpon && data.sponsor_amount !== undefined) kpiSpon.textContent = money(data.sponsor_amount);
            if (sponSub && data.sponsor_amount !== undefined) sponSub.textContent = money(data.sponsor_amount);
            if (debtRemaining && data.debt !== undefined) debtRemaining.textContent = money(data.debt);

            // Render Products Tab
            if (tabId === 'products' || tabId === 'all') {
                if (drinksList) {
                    if (data.popular_drinks && data.popular_drinks.length > 0) {
                        drinksList.innerHTML = data.popular_drinks.map((d, idx) => `
                            <div class="flex items-center justify-between p-2.5 bg-surface-container-low rounded border border-outline-variant/60">
                                <div class="flex items-center gap-2">
                                    ${rankBadge(idx)}
                                    <span class="font-bold text-on-surface">${escapeHtml(d.item_name)}</span>
                                </div>
                                <span class="font-mono font-bold text-primary">${escapeHtml(d.quantity)}</span>
                            </div>
                        `).join('');
                    } else {
                        drinksList.innerHTML = emptyStateHtml('local_cafe', escapeHtml(i18n.noDrinksTitle), escapeHtml(i18n.noDrinksDesc));
                    }
                }

                if (storesList) {
                    if (data.popular_stores && data.popular_stores.length > 0) {
                        storesList.innerHTML = data.popular_stores.map((s, idx) => `
                            <div class="flex items-center justify-between p-2.5 bg-surface-container-low rounded border border-outline-variant/60">
                                <div class="flex items-center gap-2">
                                    ${rankBadge(idx)}
                                    <span class="font-bold text-on-surface">${escapeHtml(s.restaurant)}</span>
                                </div>
                                <span class="font-mono font-bold text-on-surface">${escapeHtml(s.orders)} (${money(s.spending)})</span>
                            </div>
                        `).join('');
                    } else {
                        storesList.innerHTML = emptyStateHtml('storefront', escapeHtml(i18n.noStoresTitle), escapeHtml(i18n.noStoresDesc));
                    }
                }
            }

            // Render Debts Tab
            if (tabId === 'debts' || tabId === 'all') {
                if (debtsList) {
                    if (data.debts_by_user && data.debts_by_user.length > 0) {
                        const columns = [
                            { label: i18n.member, width: '30%' },
                            { label: i18n.debtCount, align: 'center', width: '9%' },
                            { label: i18n.totalOriginal, align: 'right', width: '14%' },
                            { label: i18n.totalPaid, align: 'right', width: '14%' },
                            { label: i18n.totalRemaining, align: 'right', width: '14%' },
                            { label: i18n.status, align: 'center', width: '19%' },
                        ];
                        const rows = data.debts_by_user.map((u) => {
                            const remaining = Number(u.outstanding_debt || 0);
                            const isCleared = remaining <= 0;
                            return [
                                memberCellHtml(u.user_name, u.user_email),
                                `<span class="font-mono font-semibold text-on-surface">${escapeHtml(u.debt_count)}</span>`,
                                `<span class="font-mono font-semibold text-outline">${money(u.total_original)}</span>`,
                                `<span class="font-mono font-semibold text-emerald-600">${money(u.total_paid)}</span>`,
                                `<span class="font-mono font-bold ${isCleared ? 'text-outline' : 'text-error'}">${money(remaining)}</span>`,
                                isCleared
                                    ? `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"><span class="material-symbols-outlined text-[12px]">check_circle</span>${escapeHtml(i18n.statusCleared)}</span>`
                                    : `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20"><span class="material-symbols-outlined text-[12px]">schedule</span>${escapeHtml(i18n.statusOwing)}</span>`,
                            ];
                        });
                        // Wider member/status columns: keep the money columns readable on narrow screens by scrolling instead.
                        debtsList.innerHTML = reportTableHtml(columns, rows, { minWidth: '44rem' });
                    } else {
                        debtsList.innerHTML = emptyStateHtml('check_circle', escapeHtml(i18n.noDebtsTitle), escapeHtml(i18n.noDebtsDesc));
                    }
                }
            }

            // Render Sponsors Tab
            if (tabId === 'sponsors' || tabId === 'all') {
                if (sponsorsList) {
                    if (data.sponsors_leaderboard && data.sponsors_leaderboard.length > 0) {
                        const columns = [
                            { label: i18n.rank, align: 'center', width: '10%' },
                            { label: i18n.sponsor, width: '42%' },
                            { label: i18n.sponsoredOrders, align: 'center', width: '20%' },
                            { label: i18n.totalSponsored, align: 'right', width: '28%' },
                        ];
                        const rankClass = (idx) => idx === 0 ? 'bg-amber-400 text-amber-950 shadow-xs'
                            : idx === 1 ? 'bg-slate-300 text-slate-800'
                            : idx === 2 ? 'bg-amber-700/20 text-amber-800'
                            : 'bg-surface-container text-outline';
                        const rows = data.sponsors_leaderboard.map((s, idx) => [
                            `<span class="w-6 h-6 rounded-full inline-flex items-center justify-center font-bold text-xs ${rankClass(idx)}">${idx + 1}</span>`,
                            memberCellHtml(s.user_name, s.user_email),
                            `<span class="font-mono font-semibold text-on-surface">${escapeHtml(s.sponsored_campaigns)}</span>`,
                            `<span class="font-mono font-bold text-emerald-600">${money(s.total_sponsored)}</span>`,
                        ]);
                        sponsorsList.innerHTML = reportTableHtml(columns, rows);
                    } else {
                        sponsorsList.innerHTML = emptyStateHtml('volunteer_activism', escapeHtml(i18n.noSponsorsTitle), escapeHtml(i18n.noSponsorsDesc));
                    }
                }
            }

            // Render Receiving Accounts Tab
            if (tabId === 'accounts' || tabId === 'all') {
                if (accountsList) {
                    if (data.payment_accounts && data.payment_accounts.length > 0) {
                        const columns = [
                            { label: i18n.paymentAccount, width: '40%' },
                            { label: i18n.ordersCount, align: 'center', width: '14%' },
                            { label: i18n.totalReceived, align: 'right', width: '23%' },
                            { label: i18n.totalOutstanding, align: 'right', width: '23%' },
                        ];
                        const rows = data.payment_accounts.map((a) => {
                            const outstanding = Number(a.total_outstanding || 0);
                            const accountCell = a.payment_account_id === null
                                ? `<div class="font-semibold text-outline italic">${escapeHtml(i18n.accountUnassigned)}</div>`
                                : `
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="shrink-0 px-1.5 py-0.5 rounded bg-primary/10 text-primary font-mono font-bold text-[10px]">${escapeHtml(a.bank_code)}</span>
                                        <span data-account-number data-masked="${escapeHtml(a.account_number)}" data-full="${escapeHtml(a.account_number_full || a.account_number)}" class="font-mono font-semibold text-on-surface truncate">${escapeHtml(a.account_number)}</span>
                                        <button type="button" data-account-number-toggle aria-pressed="false"
                                            title="${escapeHtml(i18n.toggleAccountNumber || '')}" aria-label="${escapeHtml(i18n.toggleAccountNumber || '')}"
                                            class="inline-flex shrink-0 items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>
                                        </button>
                                    </div>
                                    <div class="text-[11px] text-outline uppercase truncate">${escapeHtml(a.account_name)}</div>
                                `;
                            return [
                                accountCell,
                                `<span class="font-mono font-semibold text-on-surface">${escapeHtml(a.order_count)}</span>`,
                                `<span class="font-mono font-bold text-emerald-600">${money(a.total_received)}</span>`,
                                `<span class="font-mono font-bold ${outstanding > 0 ? 'text-error' : 'text-outline'}">${money(outstanding)}</span>`,
                            ];
                        });
                        accountsList.innerHTML = reportTableHtml(columns, rows, { minWidth: '36rem' });
                    } else {
                        accountsList.innerHTML = emptyStateHtml('account_balance', escapeHtml(i18n.noAccountsTitle), escapeHtml(i18n.noAccountsDesc));
                    }
                }
            }

            // Render Users Tab
            if (tabId === 'users' || tabId === 'all') {
                if (usersList) {
                    if (data.top_users && data.top_users.length > 0) {
                        const columns = [
                            { label: '#', align: 'center', width: '10%' },
                            { label: i18n.member, width: '42%' },
                            { label: i18n.ordersPlaced, align: 'center', width: '20%' },
                            { label: i18n.totalSpent, align: 'right', width: '28%' },
                        ];
                        const rows = data.top_users.map((u, idx) => [
                            `<span class="font-mono font-bold text-outline">${idx + 1}</span>`,
                            memberCellHtml(u.user_name, u.user_email),
                            `<span class="font-mono font-semibold text-on-surface">${escapeHtml(u.order_count)}</span>`,
                            `<span class="font-mono font-bold text-primary">${money(u.total_spent)}</span>`,
                        ]);
                        usersList.innerHTML = reportTableHtml(columns, rows);
                    } else {
                        usersList.innerHTML = emptyStateHtml('group', escapeHtml(i18n.noUsersTitle), escapeHtml(i18n.noUsersDesc));
                    }
                }
            }

            loadedTabCache.add(cacheKey);
        } catch(e) {
            console.error('Error loading tab report:', e);
        }
    };

    window.loadReportData = async function() {
        // Clear cache on full reload or date range change
        loadedTabCache.clear();
        await window.loadTabReportData(currentActiveTab, true);
    };

    window.exportReportCSV = function() {
        const queryParams = new URLSearchParams();
        const dateFrom = dateRangePicker?.querySelector('.date-from-hidden')?.value || '';
        const dateTo = dateRangePicker?.querySelector('.date-to-hidden')?.value || '';
        if (dateFrom) queryParams.set('date_from', dateFrom);
        if (dateTo) queryParams.set('date_to', dateTo);
        if (!dateFrom && !dateTo) queryParams.set('period', 'all');
        const queryString = queryParams.toString() ? `?${queryParams.toString()}` : '';

        window.location.assign(`/admin/${roomSlug}/reports/export${queryString}`);
    };

    document.addEventListener('admin:daterange-change', () => {
        window.loadReportData();
    });
}

