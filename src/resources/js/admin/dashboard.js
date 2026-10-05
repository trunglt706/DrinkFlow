import { formatMoney } from '../shared/money';
import { attachSocketDebugLogger } from '../shared/socket-debug';
import { setLoadingOverlay } from '../shared/loading-overlay';

/**
 * Admin Dashboard Live Monitor & Realtime Controller
 */
export function initAdminDashboard() {
    const dashboardEl = document.querySelector('[data-admin-dashboard]');
    if (!dashboardEl) return;

    const dashboardUrl = dashboardEl.dataset.dashboardUrl || '';
    const socketTokenUrl = dashboardEl.dataset.tokenUrl || '';
    const realtimeUrl = dashboardEl.dataset.realtimeUrl || 'http://localhost:3001';
    const timeExpiredText = dashboardEl.dataset.timeExpiredText || 'Time expired';
    const noDeadlineText = dashboardEl.dataset.noDeadlineText || 'No deadline';
    const openedAtText = dashboardEl.dataset.openedAtText || '';
    const todayText = dashboardEl.dataset.todayText || '';
    const acrossMembersText = dashboardEl.dataset.acrossMembersText || ':count';
    const pendingUsersText = dashboardEl.dataset.pendingUsersText || ':count';
    const storeLabelText = dashboardEl.dataset.storeLabelText || '';
    const roomFundText = dashboardEl.dataset.roomFundText || '';
    const liveCampaignText = dashboardEl.dataset.liveCampaignText || '';
    const secondaryCampaignText = dashboardEl.dataset.secondaryCampaignText || '';

    const money = formatMoney;
    const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
    let timerInterval = null;

    function renderCountdown(deadline) {
        if (timerInterval) clearInterval(timerInterval);
        const timerEl = document.querySelector('#hero-timer');
        const closingTextEl = document.querySelector('#metric-campaign-closing-text');

        if (!deadline) {
            if (timerEl) timerEl.textContent = '--:--:--';
            if (closingTextEl) closingTextEl.textContent = noDeadlineText;
            return;
        }

        const tick = () => {
            const diff = Math.floor((new Date(deadline) - Date.now()) / 1000);
            if (diff <= 0) {
                if (timerEl) timerEl.textContent = timeExpiredText;
                if (closingTextEl) closingTextEl.textContent = timeExpiredText;
                if (timerInterval) clearInterval(timerInterval);
                return false;
            }

            const hours = String(Math.floor(diff / 3600)).padStart(2, '0');
            const mins = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const secs = String(diff % 60).padStart(2, '0');
            const timeStr = `${hours}:${mins}:${secs}`;
            if (timerEl) timerEl.textContent = timeStr;
            if (closingTextEl) closingTextEl.textContent = `${mins}m ${secs}s`;
            return true;
        };

        if (tick()) {
            timerInterval = setInterval(tick, 1000);
        }
    }

    const chartLabels = {
        campaigns: dashboardEl.dataset.chartLabelCampaigns || 'Campaigns',
        spending: dashboardEl.dataset.chartLabelSpending || 'Total spending',
        orders: dashboardEl.dataset.chartLabelOrders || 'Orders',
        peak: dashboardEl.dataset.chartPeakLabel || 'Peak',
        noData: dashboardEl.dataset.chartNoDataText || '',
        summary: dashboardEl.dataset.chartSummaryTemplate || ':campaigns / :amount',
    };
    const COLOR_CAMPAIGNS = '#006948';
    const COLOR_SPENDING = '#2563eb';

    /** Round a value up to a "nice" axis maximum (1, 2, 5 x 10^n). */
    function niceMax(value) {
        if (value <= 0) return 1;
        const pow = Math.pow(10, Math.floor(Math.log10(value)));
        const n = value / pow;
        return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow;
    }

    /** Compact money label for the spending axis (e.g. 2 Tr, 1.5M). */
    function compactMoney(value) {
        const locale = document.documentElement.lang || 'vi';
        return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value || 0));
    }

    let lastTrendArgs = null;
    let trendResizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(trendResizeTimer);
        trendResizeTimer = setTimeout(() => {
            if (lastTrendArgs) renderTrendChart(...lastTrendArgs);
        }, 150);
    });

    function renderTrendChart(weeklyTrend, totalCampaigns, totalSpending) {
        lastTrendArgs = [weeklyTrend, totalCampaigns, totalSpending];
        const wrapper = document.querySelector('#svg-chart-wrapper');
        const labelsContainer = document.querySelector('#chart-day-labels');
        if (!wrapper) return;

        if (!weeklyTrend || !weeklyTrend.length) {
            wrapper.innerHTML = `<div class="h-full flex items-center justify-center text-outline text-xs font-mono">${esc(chartLabels.noData)}</div>`;
            return;
        }

        const badge = document.querySelector('#chart-summary-badge');
        if (badge) {
            badge.textContent = chartLabels.summary
                .replace(':campaigns', String(totalCampaigns || 0))
                .replace(':amount', money(totalSpending));
        }

        const rows = weeklyTrend.map(item => ({
            day: item.day_name || item.day || '',
            date: item.date || '',
            count: Number(item.campaigns_count || item.count || 0),
            spend: Number(item.spending_amount || item.spending || 0),
            orders: Number(item.orders_count || 0),
            peak: Boolean(item.is_peak),
        }));

        const maxC = Math.max(4, Math.ceil(Math.max(...rows.map(r => r.count)) / 2) * 2);
        const maxS = niceMax(Math.max(...rows.map(r => r.spend), 100000));

        // Draw in the wrapper's real pixel size so the SVG fills the frame and lines up with the
        // day labels below (they use the same 60px side margins).
        const width = Math.max(wrapper.clientWidth, 680);
        const height = Math.max(wrapper.clientHeight, 240);
        const topPad = 40;
        const bottomPad = height - 20;
        const leftPad = 60;
        const rightPad = width - 60;
        const midY = (topPad + bottomPad) / 2;
        const stepX = (rightPad - leftPad) / Math.max(rows.length - 1, 1);
        const colW = rows.length > 1 ? stepX : rightPad - leftPad;
        const barW = 32;

        const points = rows.map((r, idx) => ({
            x: leftPad + idx * stepX,
            y: bottomPad - (r.spend / maxS) * (bottomPad - topPad),
        }));

        const barsHtml = rows.map((r, idx) => {
            const barH = (r.count / maxC) * (bottomPad - topPad);
            const barY = bottomPad - barH;
            return `
                <rect x="${points[idx].x - barW / 2}" y="${barY}" width="${barW}" height="${barH}" rx="3" fill="${COLOR_CAMPAIGNS}" opacity="0.85"></rect>
                ${r.count > 0 ? `<text x="${points[idx].x}" y="${barY - 5}" text-anchor="middle" font-size="10" font-family="Inter, sans-serif" font-weight="bold" fill="${COLOR_CAMPAIGNS}">${r.count}</text>` : ''}
            `;
        }).join('');

        const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
        const areaPath = `${linePath} L ${points[points.length - 1].x} ${bottomPad} L ${points[0].x} ${bottomPad} Z`;

        const gridY = [topPad, midY, bottomPad];
        const gridHtml = gridY.map((y, i) => `
            <line x1="${leftPad}" y1="${y}" x2="${rightPad}" y2="${y}" stroke="currentColor" class="${i === 2 ? 'text-outline-variant/60' : 'text-outline-variant/30'}" stroke-width="1" ${i === 2 ? '' : 'stroke-dasharray="3 3"'}></line>
            <text x="${leftPad - barW / 2 - 6}" y="${y + 3}" text-anchor="end" font-size="9" font-family="Inter, sans-serif" fill="${COLOR_CAMPAIGNS}">${i === 0 ? maxC : i === 1 ? maxC / 2 : 0}</text>
            <text x="${rightPad + barW / 2 + 6}" y="${y + 3}" text-anchor="start" font-size="9" font-family="Inter, sans-serif" fill="${COLOR_SPENDING}">${compactMoney(i === 0 ? maxS : i === 1 ? maxS / 2 : 0)}</text>
        `).join('');

        const peakRow = rows.findIndex(r => r.peak && r.spend > 0);
        let peakMarker = '';
        if (peakRow >= 0) {
            const p = points[peakRow];
            const label = `${chartLabels.peak}: ${rows[peakRow].day}`;
            const boxW = Math.max(90, label.length * 6.5 + 16);
            const boxX = Math.min(Math.max(p.x - boxW / 2, 0), width - boxW);
            peakMarker = `
                <g class="pointer-events-none">
                    <circle cx="${p.x}" cy="${p.y}" r="6" fill="${COLOR_SPENDING}" stroke="#ffffff" stroke-width="2"></circle>
                    <rect x="${boxX}" y="${Math.max(2, p.y - 32)}" width="${boxW}" height="20" rx="4" fill="#0f172a" opacity="0.9"></rect>
                    <text x="${boxX + boxW / 2}" y="${Math.max(2, p.y - 32) + 14}" text-anchor="middle" font-size="10" font-weight="bold" font-family="Inter, sans-serif" fill="#bfdbfe">${esc(label)}</text>
                </g>
            `;
        }

        const hitHtml = rows.map((r, idx) => `
            <g class="group" data-trend-idx="${idx}">
                <rect x="${points[idx].x - colW / 2}" y="0" width="${colW}" height="${height}" fill="${COLOR_SPENDING}" class="opacity-0 group-hover:opacity-[0.07] transition-opacity cursor-pointer"></rect>
                <circle cx="${points[idx].x}" cy="${points[idx].y}" r="4" fill="#ffffff" stroke="${COLOR_SPENDING}" stroke-width="2" class="pointer-events-none"></circle>
            </g>
        `).join('');

        wrapper.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" class="w-full h-full overflow-visible">
                <defs>
                    <linearGradient id="spendingGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="${COLOR_SPENDING}" stop-opacity="0.22"/>
                        <stop offset="100%" stop-color="${COLOR_SPENDING}" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                ${gridHtml}
                ${barsHtml}
                <path d="${areaPath}" fill="url(#spendingGrad)" class="pointer-events-none"></path>
                <path d="${linePath}" fill="none" stroke="${COLOR_SPENDING}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none"></path>
                ${peakMarker}
                ${hitHtml}
            </svg>
            <div data-trend-tooltip class="pointer-events-none absolute z-20 hidden min-w-[190px] rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[11px] shadow-lg"></div>
        `;

        bindTrendTooltip(wrapper, rows, points);

        if (labelsContainer) {
            // Position each label at the same x-percentage as its bar/point above (edge-to-edge,
            // not equal grid cells), otherwise the first/last labels drift away from their bars.
            labelsContainer.innerHTML = rows.map((r, idx) => {
                const percent = rows.length > 1 ? (idx / (rows.length - 1)) * 100 : 50;
                return `
                    <div class="absolute top-2 -translate-x-1/2 text-center" style="left:${percent}%">
                        <span class="text-xs font-semibold text-on-surface whitespace-nowrap">${esc(r.day)}</span>
                        <span class="block text-[10px] font-mono text-outline whitespace-nowrap">${esc(r.date)}</span>
                    </div>
                `;
            }).join('');
        }
    }

    /** Show a rich tooltip (campaigns, spending, orders) while hovering a day column. */
    function bindTrendTooltip(wrapper, rows, points) {
        const svg = wrapper.querySelector('svg');
        const tooltip = wrapper.querySelector('[data-trend-tooltip]');
        if (!svg || !tooltip) return;

        const show = idx => {
            const r = rows[idx];
            tooltip.innerHTML = `
                <div class="mb-1.5 flex items-center justify-between gap-3 border-b border-outline-variant/60 pb-1.5">
                    <span class="text-xs font-semibold text-on-surface">${esc(r.day)}</span>
                    <span class="font-mono text-outline">${esc(r.date)}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="flex items-center gap-1.5 text-on-surface-variant"><span class="h-2 w-2 rounded-sm" style="background:${COLOR_CAMPAIGNS}"></span>${esc(chartLabels.campaigns)}</span>
                    <strong class="font-mono text-on-surface">${r.count}</strong>
                </div>
                <div class="mt-1 flex items-center justify-between gap-4">
                    <span class="flex items-center gap-1.5 text-on-surface-variant"><span class="h-2 w-2 rounded-full" style="background:${COLOR_SPENDING}"></span>${esc(chartLabels.spending)}</span>
                    <strong class="font-mono" style="color:${COLOR_SPENDING}">${money(r.spend)}</strong>
                </div>
                <div class="mt-1 flex items-center justify-between gap-4">
                    <span class="text-on-surface-variant">${esc(chartLabels.orders)}</span>
                    <strong class="font-mono text-on-surface">${r.orders}</strong>
                </div>
            `;
            tooltip.classList.remove('hidden');

            // Convert the SVG point to wrapper coordinates so it stays correct when the SVG is letterboxed.
            const ctm = svg.getScreenCTM();
            if (!ctm) return;
            const pt = svg.createSVGPoint();
            pt.x = points[idx].x;
            pt.y = points[idx].y;
            const screen = pt.matrixTransform(ctm);
            const box = wrapper.getBoundingClientRect();
            const tipW = tooltip.offsetWidth;
            const tipH = tooltip.offsetHeight;
            let left = screen.x - box.left - tipW / 2;
            left = Math.max(0, Math.min(left, box.width - tipW));
            let top = screen.y - box.top - tipH - 14;
            if (top < 0) top = screen.y - box.top + 14;
            // Keep the tooltip inside the wrapper: the scroll container clips anything below it.
            top = Math.max(0, Math.min(top, box.height - tipH));
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        };

        svg.querySelectorAll('[data-trend-idx]').forEach(group => {
            const idx = Number(group.dataset.trendIdx);
            group.addEventListener('mouseenter', () => show(idx));
            group.addEventListener('click', () => show(idx));
        });
        wrapper.onmouseleave = () => tooltip.classList.add('hidden');
    }

    function renderDashboard(data) {
        if (!data) return;

        // KPI
        const activeRooms = document.querySelector('#metric-active-rooms');
        if (activeRooms) activeRooms.textContent = data.active_rooms || 1;

        const roomsHint = document.querySelector('#metric-rooms-hint');
        if (roomsHint) roomsHint.textContent = acrossMembersText.replace(':count', String(data.active_room_users || 0));

        const liveCmp = document.querySelector('#metric-live-campaigns');
        if (liveCmp) liveCmp.textContent = data.active_campaigns || 0;

        const ordersToday = document.querySelector('#metric-orders-today');
        if (ordersToday) ordersToday.textContent = data.orders_today || 0;

        const ordersGrowth = document.querySelector('#metric-orders-growth-val');
        if (ordersGrowth) ordersGrowth.textContent = `${data.orders_growth_percent >= 0 ? '+' : ''}${data.orders_growth_percent || 0}%`;

        const totalValue = document.querySelector('#metric-total-value');
        if (totalValue) totalValue.textContent = money(data.today_total_value);

        const sponsorsVal = document.querySelector('#metric-sponsors-val');
        if (sponsorsVal) sponsorsVal.textContent = money(data.today_sponsor_value);

        const unpaidDebt = document.querySelector('#metric-unpaid-debt');
        if (unpaidDebt) unpaidDebt.textContent = money(data.outstanding_debts);

        const debtUsers = document.querySelector('#metric-debt-users');
        if (debtUsers) debtUsers.textContent = pendingUsersText.replace(':count', String(data.pending_debt_users_count || 0));

        // Nav Badge
        const navBadge = document.querySelector('#nav-live-badge');
        if (navBadge) {
            if (data.active_campaigns > 0) {
                navBadge.textContent = `${data.active_campaigns} Live`;
                navBadge.classList.remove('hidden');
            } else {
                navBadge.classList.add('hidden');
            }
        }

        // Weekly Trend Chart
        renderTrendChart(data.weekly_trend, data.weekly_total_campaigns, data.weekly_total_spending);

        // Campaign Control Panels
        const campaignPanels = document.querySelector('#campaign-panels-container');
        const hero = data.active_campaign;
        const sec = data.secondary_campaign;

        if (hero) {
            if (campaignPanels) campaignPanels.classList.remove('hidden');
            const heroCard = document.querySelector('#hero-campaign-card');
            const secCard = document.querySelector('#secondary-campaign-card');

            if (heroCard) {
                heroCard.classList.remove('hidden');
                if (sec) {
                    heroCard.classList.remove('lg:col-span-3');
                    heroCard.classList.add('lg:col-span-2');
                } else {
                    heroCard.classList.remove('lg:col-span-2');
                    heroCard.classList.add('lg:col-span-3');
                }
            }

            const heroTitle = document.querySelector('#hero-campaign-title');
            const heroCode = document.querySelector('#hero-campaign-code');
            const heroTime = document.querySelector('#hero-campaign-time');
            const heroNet = document.querySelector('#hero-net-payable');
            const heroGross = document.querySelector('#hero-gross-subtotal');
            const heroSponsor = document.querySelector('#hero-sponsor-total');
            const heroPartText = document.querySelector('#hero-participation-text');
            const heroBar = document.querySelector('#hero-progress-bar');
            const heroSponsorNames = document.querySelector('#hero-sponsor-names');

            if (heroTitle) heroTitle.textContent = hero.name || liveCampaignText;
            if (heroCode) heroCode.textContent = hero.code || `#CMP-${hero.id}`;
            if (heroTime) heroTime.textContent = hero.started_at ? openedAtText.replace(':time', new Date(hero.started_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})) : todayText;

            const gross = Number(hero.total_amount || 0);
            const sponsor = Number(hero.sponsor_total || 0);
            const net = Math.max(0, gross - sponsor);

            if (heroNet) heroNet.textContent = money(net);
            if (heroGross) heroGross.textContent = `Gross: ${money(gross)}`;
            if (heroSponsor) heroSponsor.textContent = `-${money(sponsor)}`;

            const participants = data.active_campaign_participants || hero.orders_count || 0;
            const totalMembers = data.active_room_users || 1;
            const percent = data.participation_percent || Math.min(100, Math.round((participants / totalMembers) * 100));

            if (heroPartText) heroPartText.textContent = `${participants} / ${totalMembers} (${percent}%)`;
            if (heroBar) {
                heroBar.style.width = `${percent}%`;
                // Same bands as the reports' participation ratio: < 50% rose, < 80% amber, otherwise emerald.
                const band = percent < 50 ? 'low' : (percent < 80 ? 'medium' : 'high');
                heroBar.classList.remove('bg-primary', 'bg-rose-500', 'bg-amber-500', 'bg-emerald-500');
                heroBar.classList.add({ low: 'bg-rose-500', medium: 'bg-amber-500', high: 'bg-emerald-500' }[band]);
                heroBar.dataset.participationBand = band;
            }

            renderCountdown(hero.deadline);

            const closeCampaignBtn = document.querySelector('#btn-open-close-modal');
            if (closeCampaignBtn) closeCampaignBtn.dataset.campaignId = String(hero.id);

            if (heroSponsorNames) {
                heroSponsorNames.innerHTML = `
                    <span>Sponsor: <strong>${money(sponsor)}</strong></span>
                `;
            }

            if (sec && secCard) {
                secCard.classList.remove('hidden', 'opacity-50');
                const secCode = document.querySelector('#sec-campaign-code');
                const secTitle = document.querySelector('#sec-campaign-title');
                const secVendor = document.querySelector('#sec-campaign-vendor');
                const secDeadline = document.querySelector('#sec-campaign-deadline');
                const secOrders = document.querySelector('#sec-campaign-orders');
                const secSubtotal = document.querySelector('#sec-campaign-subtotal');

                if (secCode) secCode.textContent = sec.code || `#CMP-${sec.id}`;
                if (secTitle) secTitle.textContent = sec.name || secondaryCampaignText;
                if (secVendor) secVendor.textContent = storeLabelText.replace(':name', sec.restaurant || '—');
                if (secDeadline) secDeadline.textContent = sec.deadline ? new Date(sec.deadline).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'}) : todayText;
                if (secOrders) secOrders.textContent = `${sec.orders_count || 0}`;
                if (secSubtotal) secSubtotal.textContent = money(sec.total_amount);
            } else if (secCard) {
                secCard.classList.add('hidden');
            }
        } else {
            if (campaignPanels) campaignPanels.classList.add('hidden');
        }

        // Payment account
        const acc = (data.payment_accounts || [])[0];
        if (acc) {
            const pBank = document.querySelector('#payment-bank-name');
            const pMasked = document.querySelector('#payment-account-masked');
            if (pBank) pBank.textContent = `${acc.bank_name || acc.bank_code} (${acc.account_name || roomFundText})`;
            if (pMasked) pMasked.textContent = acc.account_number_masked || '•••• •••• ••••';
        }
    }

    const trendLoading = document.querySelector('[data-trend-loading]');

    async function loadDashboard() {
        if (!dashboardUrl) {
            setLoadingOverlay(trendLoading, false);
            return;
        }
        setLoadingOverlay(trendLoading, true);
        try {
            const res = await fetch(dashboardUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load dashboard data');
            const json = await res.json();
            renderDashboard(json.data || {});
        } catch (e) {
            console.error('Dashboard load error', e);
        } finally {
            setLoadingOverlay(trendLoading, false);
        }
    }

    loadDashboard();

    // The shared close-campaign modal re-fetches its own summary; after closing, refresh the dashboard in place.
    window.addEventListener('admin:campaign-closed', event => {
        event.preventDefault();
        loadDashboard();
    });

    let socketReloadDebounceTimer = null;
    function debouncedLoadDashboard(delayMs = 2500) {
        clearTimeout(socketReloadDebounceTimer);
        socketReloadDebounceTimer = setTimeout(() => {
            loadDashboard();
        }, delayMs);
    }

    // Socket.IO Realtime
    if (socketTokenUrl) {
        fetch(socketTokenUrl, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(({ data }) => {
                if (!window.io) return;
                const socket = window.io(realtimeUrl, {
                    auth: { token: data.token },
                    transports: ['websocket', 'polling']
                });
                attachSocketDebugLogger(socket, 'admin');

                socket.on('connect', () => {
                    const stateEl = document.querySelector('#socket-state');
                    if (stateEl) {
                        stateEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span>Socket Live</span>';
                        stateEl.classList.remove('text-outline');
                        stateEl.classList.add('text-emerald-700', 'bg-emerald-50', 'border-emerald-200');
                    }
                });

                [
                    'order.created', 'order.updated', 'order.deleted',
                    'campaign.created', 'campaign.updated', 'campaign.deleted', 'campaign.closed',
                    'campaign.menu.updated', 'campaign.menu.deleted', 'campaign.participant.declined', 'campaign.participant.rejoined'
                ].forEach(ev => {
                    socket.on(ev, () => {
                        const stream = document.querySelector('#activity-stream');
                        if (stream) {
                            stream.insertAdjacentHTML('afterbegin', `
                                <div class="p-2.5 rounded bg-emerald-50 border border-emerald-200 text-xs flex items-center justify-between">
                                    <span class="text-emerald-800 font-semibold">${esc(ev)}</span>
                                    <span class="text-[10px] font-mono text-emerald-600">${new Date().toLocaleTimeString('vi-VN')}</span>
                                </div>
                            `);
                            while (stream.children.length > 50) {
                                stream.lastElementChild?.remove();
                            }
                        }
                        debouncedLoadDashboard(2500);
                    });
                });
            })
            .catch(() => {
                const stateEl = document.querySelector('#socket-state');
                if (stateEl) stateEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-300"></span><span>Socket Offline</span>';
            });
    }
}
