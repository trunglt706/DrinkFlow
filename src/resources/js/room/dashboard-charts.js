import { formatMoney } from '../shared/money';
import { setLoadingOverlay } from '../shared/loading-overlay';

const COLOR_ITEMS = '#006948';
const COLOR_VALUE = '#2563eb';

const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));

/** Round a value up to a "nice" axis maximum (1, 2, 5 x 10^n). */
function niceMax(value) {
    if (value <= 0) return 1;
    const pow = Math.pow(10, Math.floor(Math.log10(value)));
    const n = value / pow;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow;
}

function parseJsonAttr(el, attr, fallback) {
    try {
        const raw = el.dataset[attr];
        return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
        return fallback;
    }
}

// Categorical slots in fixed order (validated: adjacent CVD ΔE >= 9.1, normal-vision ΔE >= 19.6 on white).
// Slot i always belongs to rank i; beyond the palette the remaining sponsors share one neutral "others" slice.
const SPONSOR_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7'];
const COLOR_OTHERS = '#94a3b8';

const ACTIVE_TOGGLE_CLASSES = ['bg-white', 'text-[#006948]', 'shadow-2xs'];
const INACTIVE_TOGGLE_CLASSES = ['text-slate-500', 'hover:text-slate-700'];

/** Sponsors with a positive amount, highest first (the server already sorts them). */
function normalizeSponsors(sponsors) {
    return (sponsors || [])
        .map(s => ({
            name: s.name || '',
            amount: Number(s.amount || 0),
            campaigns: Number(s.sponsored_campaigns || 0),
        }))
        .filter(r => r.amount > 0);
}

function renderEmpty(container, icon, message) {
    container.innerHTML = `
        <div class="h-full min-h-[200px] flex flex-col items-center justify-center text-center gap-2 text-slate-400">
            <span class="material-symbols-outlined text-[26px] text-slate-300" aria-hidden="true">${icon}</span>
            <p class="text-xs">${esc(message)}</p>
        </div>
    `;
}

/** Render the full sponsor ranking of the month as a scrollable table. */
function renderTopSponsorsTable(container, sponsors, labels) {
    if (!container) return;

    const rows = normalizeSponsors(sponsors);
    const total = rows.reduce((sum, r) => sum + r.amount, 0);
    if (!rows.length || total <= 0) {
        renderEmpty(container, 'volunteer_activism', labels.noSponsorData);
        return;
    }

    const bodyHtml = rows.map((r, idx) => `
        <tr class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50">
            <td class="py-1.5 pr-2 font-mono text-[10px] text-slate-400">${idx + 1}</td>
            <td class="max-w-0 py-1.5 pr-2"><span class="block truncate text-slate-700" title="${esc(r.name)}">${esc(r.name)}</span></td>
            <td class="py-1.5 pr-2 text-right font-mono text-slate-500">${r.campaigns}</td>
            <td class="py-1.5 pr-2 text-right font-mono font-semibold text-slate-900 whitespace-nowrap">${esc(formatMoney(r.amount))}</td>
            <td class="py-1.5 text-right font-mono text-[10px] text-slate-400">${Math.round((r.amount / total) * 1000) / 10}%</td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="max-h-[260px] overflow-y-auto">
            <table class="w-full table-fixed text-[11px]">
                <colgroup><col class="w-7"><col><col class="w-16"><col class="w-24"><col class="w-12"></colgroup>
                <thead class="sticky top-0 bg-white">
                    <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-400">
                        <th class="py-1.5 pr-2 text-left font-semibold">${esc(labels.sponsorColRank)}</th>
                        <th class="py-1.5 pr-2 text-left font-semibold">${esc(labels.sponsorColName)}</th>
                        <th class="py-1.5 pr-2 text-right font-semibold">${esc(labels.sponsorColCampaigns)}</th>
                        <th class="py-1.5 pr-2 text-right font-semibold">${esc(labels.sponsorColAmount)}</th>
                        <th class="py-1.5 text-right font-semibold">${esc(labels.sponsorColShare)}</th>
                    </tr>
                </thead>
                <tbody>${bodyHtml}</tbody>
                <tfoot>
                    <tr class="border-t border-slate-200">
                        <td colspan="3" class="py-1.5 pr-2 font-semibold text-slate-500">${esc(labels.sponsorTotal)}</td>
                        <td class="py-1.5 pr-2 text-right font-mono font-bold text-slate-900 whitespace-nowrap">${esc(formatMoney(total))}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
}

/** Render the "Top nhà tài trợ" ranking as a donut chart with a legend and a hover tooltip. */
function renderTopSponsorsChart(container, sponsors, labels) {
    if (!container) return;

    let rows = normalizeSponsors(sponsors);
    const total = rows.reduce((sum, r) => sum + r.amount, 0);

    if (!rows.length || total <= 0) {
        renderEmpty(container, 'volunteer_activism', labels.noSponsorData);
        return;
    }

    // Keep one color per sponsor: past the palette size, fold the tail into a single "others" slice.
    if (rows.length > SPONSOR_COLORS.length) {
        const head = rows.slice(0, SPONSOR_COLORS.length - 1);
        const tail = rows.slice(SPONSOR_COLORS.length - 1);
        head.push({
            name: (labels.sponsorOthers || ':count').replace(':count', String(tail.length)),
            amount: tail.reduce((sum, r) => sum + r.amount, 0),
            campaigns: tail.reduce((sum, r) => sum + r.campaigns, 0),
            isOthers: true,
        });
        rows = head;
    }
    rows = rows.map((r, idx) => ({ ...r, color: r.isOthers ? COLOR_OTHERS : SPONSOR_COLORS[idx] }));

    const size = 180;
    const center = size / 2;
    const radius = 70;
    const thickness = 26;
    const circumference = 2 * Math.PI * radius;
    // 2px surface gap between adjacent slices (none when a single sponsor fills the ring).
    const gap = rows.length > 1 ? 2 : 0;
    const percent = amount => Math.round((amount / total) * 1000) / 10;

    let offset = 0;
    const slicesHtml = rows.map((r, idx) => {
        const length = (r.amount / total) * circumference;
        const visible = Math.max(0.5, length - gap);
        // Start at 12 o'clock and go clockwise.
        const html = `
            <circle data-sponsor-idx="${idx}" cx="${center}" cy="${center}" r="${radius}" fill="none"
                stroke="${r.color}" stroke-width="${thickness}"
                stroke-dasharray="${visible} ${circumference - visible}"
                stroke-dashoffset="${-offset}"
                transform="rotate(-90 ${center} ${center})"
                class="cursor-pointer transition-[stroke-width] duration-150 hover:[stroke-width:30px]"></circle>
        `;
        offset += length;
        return html;
    }).join('');

    const legendHtml = rows.map((r, idx) => `
        <li data-sponsor-idx="${idx}" class="flex items-center gap-2 rounded-md px-1.5 py-1 cursor-default hover:bg-slate-50">
            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background:${r.color}" aria-hidden="true"></span>
            <span class="min-w-0 flex-1 truncate text-[11px] text-slate-700" title="${esc(r.name)}">${esc(r.name)}</span>
            <span class="shrink-0 font-mono text-[11px] font-semibold text-slate-900">${esc(formatMoney(r.amount))}</span>
            <span class="w-11 shrink-0 text-right font-mono text-[10px] text-slate-400">${percent(r.amount)}%</span>
        </li>
    `).join('');

    container.innerHTML = `
        <div class="relative flex h-full min-h-[200px] flex-col items-center justify-center gap-4 sm:flex-row">
            <div class="relative h-[170px] w-[170px] shrink-0">
                <svg viewBox="0 0 ${size} ${size}" class="h-full w-full" role="img" aria-label="${esc(labels.sponsorTotal)}: ${esc(formatMoney(total))}">
                    ${slicesHtml}
                </svg>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-[10px] text-slate-500">${esc(labels.sponsorTotal)}</span>
                    <strong class="font-mono text-sm text-slate-900">${esc(formatMoney(total))}</strong>
                </div>
            </div>
            <ul class="w-full min-w-0 flex-1 space-y-0.5">${legendHtml}</ul>
            <div data-sponsor-tooltip class="pointer-events-none absolute z-20 hidden min-w-[170px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] shadow-lg"></div>
        </div>
    `;

    const wrapper = container.firstElementChild;
    const tooltip = container.querySelector('[data-sponsor-tooltip]');
    if (!wrapper || !tooltip) return;

    const show = (idx, event) => {
        const r = rows[idx];
        tooltip.innerHTML = `
            <div class="mb-1 flex items-center gap-1.5 font-semibold text-slate-900">
                <span class="h-2 w-2 shrink-0 rounded-sm" style="background:${r.color}"></span>${esc(r.name)}
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-slate-500">${esc(labels.value)}</span>
                <strong class="font-mono text-slate-900">${esc(formatMoney(r.amount))} <span class="font-normal text-slate-400">(${percent(r.amount)}%)</span></strong>
            </div>
            <div class="mt-0.5 text-slate-400">${esc((labels.sponsoredCampaigns || ':count').replace(':count', String(r.campaigns)))}</div>
        `;
        tooltip.classList.remove('hidden');

        const box = wrapper.getBoundingClientRect();
        const tipW = tooltip.offsetWidth;
        const tipH = tooltip.offsetHeight;
        let left = event.clientX - box.left - tipW / 2;
        left = Math.max(0, Math.min(left, box.width - tipW));
        let top = event.clientY - box.top - tipH - 12;
        if (top < 0) top = event.clientY - box.top + 16;
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    wrapper.querySelectorAll('[data-sponsor-idx]').forEach(el => {
        const idx = Number(el.dataset.sponsorIdx);
        el.addEventListener('mousemove', event => show(idx, event));
        el.addEventListener('mouseleave', () => tooltip.classList.add('hidden'));
    });
}

/** Render the current-month combo chart: bars for item count, a line for order value, one point per day. */
function renderMonthlyTrendChart(container, trend, labels, emptyMessage) {
    if (!container) return;

    const rows = (trend || []).map(item => ({
        day: String(item.day ?? ''),
        dayName: item.day_name || '',
        date: item.date || '',
        items: Number(item.items_count || 0),
        value: Number(item.value_amount || 0),
    }));

    if (!rows.length || rows.every(r => r.items === 0 && r.value === 0)) {
        renderEmpty(container, 'bar_chart', emptyMessage);
        return;
    }

    const maxItems = Math.max(4, Math.ceil(Math.max(...rows.map(r => r.items)) / 2) * 2);
    const maxValue = niceMax(Math.max(...rows.map(r => r.value), 1));

    const width = 700;
    const height = 220;
    const topPad = 20;
    const bottomPad = 165;
    const leftPad = 34;
    const rightPad = 666;
    const stepX = (rightPad - leftPad) / Math.max(rows.length - 1, 1);
    const colW = rows.length > 1 ? stepX : rightPad - leftPad;
    // Up to 31 days share the width: keep a gap between bars.
    const barW = Math.min(26, Math.max(4, colW * 0.6));
    // Label at most ~10 days on the axis (always today, the last one) so they never overlap.
    const labelEvery = Math.ceil(rows.length / 10);
    const showLabel = idx => idx === rows.length - 1
        || (idx % labelEvery === 0 && rows.length - 1 - idx >= labelEvery / 2);

    const points = rows.map((r, idx) => ({
        x: leftPad + idx * stepX,
        y: bottomPad - (r.value / maxValue) * (bottomPad - topPad),
    }));

    const barsHtml = rows.map((r, idx) => {
        const barH = (r.items / maxItems) * (bottomPad - topPad);
        const barY = bottomPad - barH;
        return `<rect x="${points[idx].x - barW / 2}" y="${barY}" width="${barW}" height="${barH}" rx="${Math.min(3, barW / 4)}" fill="${COLOR_ITEMS}" opacity="0.85"></rect>`;
    }).join('');

    const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
    // Area chart: close the value line down to the baseline so the region underneath can be filled.
    const areaPath = `${linePath} L ${points[points.length - 1].x} ${bottomPad} L ${points[0].x} ${bottomPad} Z`;
    const areaGradientId = `monthly-trend-area-${Math.random().toString(36).slice(2, 8)}`;

    const gridHtml = [topPad, bottomPad].map((y, i) => `
        <line x1="${leftPad}" y1="${y}" x2="${rightPad}" y2="${y}" stroke="currentColor" class="${i === 1 ? 'text-slate-200' : 'text-slate-100'}" stroke-width="1"></line>
    `).join('');

    const dotR = rows.length > 15 ? 3 : 4;
    const hitHtml = rows.map((r, idx) => `
        <g data-day-idx="${idx}" class="cursor-pointer">
            <rect x="${points[idx].x - colW / 2}" y="0" width="${colW}" height="${height}" fill="${COLOR_VALUE}" class="opacity-0 hover:opacity-[0.06] transition-opacity"></rect>
            <circle cx="${points[idx].x}" cy="${points[idx].y}" r="${dotR}" fill="#ffffff" stroke="${COLOR_VALUE}" stroke-width="2" class="pointer-events-none"></circle>
        </g>
    `).join('');

    const axisHtml = rows.map((r, idx) => showLabel(idx)
        ? `<span class="absolute -translate-x-1/2 text-[10px] font-semibold text-slate-500" style="left:${(points[idx].x / width) * 100}%">${esc(r.day)}</span>`
        : '').join('');

    container.innerHTML = `
        <div class="relative flex w-full h-full min-h-[200px] flex-col">
            <!-- flex-1 (not h-full) so the day labels below stay inside the card instead of overflowing it. -->
            <svg viewBox="0 0 ${width} ${height}" class="w-full min-h-0 flex-1 overflow-visible">
                <defs>
                    <linearGradient id="${areaGradientId}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="${COLOR_VALUE}" stop-opacity="0.32"></stop>
                        <stop offset="100%" stop-color="${COLOR_VALUE}" stop-opacity="0.02"></stop>
                    </linearGradient>
                </defs>
                ${gridHtml}
                <path d="${areaPath}" fill="url(#${areaGradientId})" stroke="none" class="pointer-events-none"></path>
                ${barsHtml}
                <path d="${linePath}" fill="none" stroke="${COLOR_VALUE}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none"></path>
                ${hitHtml}
            </svg>
            <div data-trend-tooltip class="pointer-events-none absolute z-20 hidden min-w-[180px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] shadow-lg"></div>
            <div class="relative mt-1 mb-3 h-4">${axisHtml}</div>
        </div>
    `;

    const wrapper = container.firstElementChild;
    const svg = container.querySelector('svg');
    const tooltip = container.querySelector('[data-trend-tooltip]');
    if (!wrapper || !svg || !tooltip) return;

    const show = idx => {
        const r = rows[idx];
        tooltip.innerHTML = `
            <div class="mb-1 flex items-center justify-between gap-3 border-b border-slate-100 pb-1">
                <span class="font-semibold text-slate-900">${esc(r.dayName)}</span>
                <span class="font-mono text-slate-400">${esc(r.date)}</span>
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="flex items-center gap-1.5 text-slate-500"><span class="h-2 w-2 rounded-sm" style="background:${COLOR_ITEMS}"></span>${esc(labels.items)}</span>
                <strong class="font-mono text-slate-900">${r.items}</strong>
            </div>
            <div class="mt-1 flex items-center justify-between gap-4">
                <span class="flex items-center gap-1.5 text-slate-500"><span class="h-2 w-2 rounded-full" style="background:${COLOR_VALUE}"></span>${esc(labels.value)}</span>
                <strong class="font-mono" style="color:${COLOR_VALUE}">${esc(formatMoney(r.value))}</strong>
            </div>
        `;
        tooltip.classList.remove('hidden');

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
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    svg.querySelectorAll('[data-day-idx]').forEach(group => {
        const idx = Number(group.dataset.dayIdx);
        group.addEventListener('mouseenter', () => show(idx));
    });
    wrapper.addEventListener('mouseleave', () => tooltip.classList.add('hidden'));
}

/**
 * Wire a segmented toggle (buttons with data-toggle-value inside a data-toggle-group element):
 * highlights the pressed button and calls onChange with its value.
 */
function initToggleGroup(group, onChange) {
    if (!group) return;

    const buttons = Array.from(group.querySelectorAll('[data-toggle-value]'));
    const select = value => {
        buttons.forEach(button => {
            const active = button.dataset.toggleValue === value;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.classList.remove(...(active ? INACTIVE_TOGGLE_CLASSES : ACTIVE_TOGGLE_CLASSES));
            button.classList.add(...(active ? ACTIVE_TOGGLE_CLASSES : INACTIVE_TOGGLE_CLASSES));
        });
        onChange(value);
    };

    buttons.forEach(button => button.addEventListener('click', () => select(button.dataset.toggleValue)));
    const initial = buttons.find(button => button.getAttribute('aria-pressed') === 'true') || buttons[0];
    if (initial) select(initial.dataset.toggleValue);
}

/** Initialize the room dashboard's sponsor ranking and current-month trend, if present on the page. */
export function initRoomDashboardCharts() {
    const root = document.querySelector('[data-room-dashboard-charts]');
    if (!root) return;

    const topSponsors = parseJsonAttr(root, 'topSponsors', []);
    const monthlyTrend = parseJsonAttr(root, 'monthlyTrend', {});
    const labels = parseJsonAttr(root, 'chartLabels', {});

    const sponsorChart = document.querySelector('#top-sponsors-chart');
    const sponsorTable = document.querySelector('#top-sponsors-table');
    const trendChart = document.querySelector('#monthly-trend-chart');

    try {
        // Both sponsor views are drawn once; the toggle only switches which one is visible.
        renderTopSponsorsChart(sponsorChart, topSponsors, labels);
        renderTopSponsorsTable(sponsorTable, topSponsors, labels);
        initToggleGroup(root.querySelector('[data-toggle-group="sponsor-view"]'), view => {
            sponsorChart?.classList.toggle('hidden', view !== 'chart');
            sponsorTable?.classList.toggle('hidden', view !== 'table');
        });

        initToggleGroup(root.querySelector('[data-toggle-group="trend-scope"]'), scope => {
            const personal = scope === 'personal';
            renderMonthlyTrendChart(
                trendChart,
                personal ? monthlyTrend.personal : monthlyTrend.room,
                labels,
                personal ? labels.noPersonalTrendData : labels.noTrendData,
            );
        });
    } finally {
        // The overlays are shown by the server until the charts have been drawn.
        root.querySelectorAll('[data-chart-loading]').forEach((overlay) => setLoadingOverlay(overlay, false));
    }
}
