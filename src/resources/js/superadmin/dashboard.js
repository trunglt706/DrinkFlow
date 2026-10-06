import { dfApi, escapeHtml, money, statusPill } from './shared';
import { sensitiveHtml } from './sensitive';

/**
 * Superadmin dashboard analytics (resources/views/superadmin/dashboard.blade.php, #sa-analytics).
 *
 * Loads GET /superadmin/dashboard/analytics (App\Services\Dashboard\SuperadminDashboardService) and renders
 * the KPI tiles with period deltas, the daily order/GMV line chart (SVG, crosshair tooltip, table view),
 * the debt-aging bars, the stuck-campaign table and the room-health table. In parallel it loads
 * GET /superadmin/dashboard/insights (SuperadminInsightsService) for the order peak-hours heatmap, the
 * security-by-severity stacked bars, event types, suspicious IPs and admin activity, and
 * GET /superadmin/dashboard/trends (SuperadminTrendsService) for signup-cohort retention, feedback ratings,
 * contact topics and infrastructure history. Every visible string comes
 * from the `data-i18n` JSON on the section (lang/{vi,en,ja}/superadmin.php → analytics).
 */

const SVG_NS = 'http://www.w3.org/2000/svg';
const CHART_HEIGHT = 220;
const CHART_PADDING = { top: 12, right: 12, bottom: 26, left: 52 };
const AGING_BUCKETS = ['0_7', '8_30', '31_60', '60_plus'];

/** KPIs where an increase is bad news (colored as a warning delta). */
const HIGHER_IS_WORSE = new Set(['high_security_events', 'outstanding_debt']);

/**
 * Replace :placeholders in a translated string.
 *
 * @param {string} text Translated text.
 * @param {Record<string, string|number>} params Replacements.
 * @returns {string}
 */
const trans = (text, params = {}) => Object.entries(params)
    .reduce((result, [key, value]) => result.replaceAll(`:${key}`, String(value)), String(text ?? ''));

const locale = () => document.documentElement.lang || undefined;
const number = (value, digits = 0) => new Intl.NumberFormat(locale(), { maximumFractionDigits: digits }).format(value || 0);

/** Compact VND for axis ticks (e.g. 1,2 Tr / 1.2M) — full amounts stay in tooltips and tables. */
const compactMoney = value => new Intl.NumberFormat(locale(), { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);

/**
 * Replace a table body with the shared empty state (components/superadmin/empty-state, cloned from
 * <template id="sa-empty-state-template">): its translated title stays, the icon and description are per table.
 *
 * @param {HTMLElement} tbody Table body to fill.
 * @param {number} columns Number of columns the row spans.
 * @param {string} icon Material Symbols icon name.
 * @param {string} description Translated description.
 */
function emptyRow(tbody, columns, icon, description) {
    const template = document.getElementById('sa-empty-state-template');
    const placeholder = template?.content.firstElementChild?.cloneNode(true);
    if (!placeholder) {
        tbody.innerHTML = `<tr><td colspan="${columns}" class="sa-empty">${escapeHtml(description)}</td></tr>`;
        return;
    }
    placeholder.querySelector('[data-empty-icon]').textContent = icon;
    placeholder.querySelector('[data-empty-description]').textContent = description;
    const cell = document.createElement('td');
    cell.colSpan = columns;
    cell.className = 'sa-empty-cell';
    cell.append(placeholder);
    const row = document.createElement('tr');
    row.append(cell);
    tbody.replaceChildren(row);
}

const formatDate = (iso, options) => new Intl.DateTimeFormat(locale(), options).format(new Date(iso));
const shortDay = iso => formatDate(`${iso}T00:00:00`, { day: '2-digit', month: '2-digit' });
const longDay = iso => formatDate(`${iso}T00:00:00`, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
const dateTime = iso => formatDate(iso, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

/**
 * Round an axis maximum up to a "nice" value so gridlines land on readable numbers.
 *
 * @param {number} max Largest data value.
 * @param {number} ticks Number of gridline intervals.
 * @param {boolean} integer Counts: keep every gridline on a whole number (no repeated rounded labels).
 * @returns {number}
 */
function niceMax(max, ticks, integer = false) {
    if (max <= 0) return ticks;
    const rough = max / ticks;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    let step = [1, 2, 2.5, 5, 10].map(m => m * magnitude).find(s => s >= rough);
    if (integer) {
        step = step < 1 ? 1 : (Number.isInteger(step) ? step : Math.ceil(step));
    }
    return step * ticks;
}

function svg(tag, attributes = {}) {
    const element = document.createElementNS(SVG_NS, tag);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    return element;
}

/**
 * Render the KPI tiles: value, signed delta vs the previous period (arrow icon + text, never color alone) and hint.
 */
function renderKpis(root, kpis, i18n) {
    const format = {
        active_rooms: value => number(value),
        active_users: value => number(value),
        gmv: value => money(value),
        collection_rate: value => (value === null ? '—' : `${number(value, 1)}%`),
        outstanding_debt: value => money(value),
        high_security_events: value => number(value),
    };

    Object.entries(kpis).forEach(([key, kpi]) => {
        const tile = root.querySelector(`[data-kpi="${key}"]`);
        if (!tile) return;
        // The security KPI is a platform-wide figure: null (hidden) for a restricted Superadmin.
        tile.hidden = key === 'high_security_events' && kpi.value === null;
        if (tile.hidden) return;
        tile.querySelector('[data-kpi-value]').textContent = format[key](kpi.value);

        if (key === 'outstanding_debt') {
            tile.querySelector('[data-kpi-hint]').textContent = trans(i18n.kpi_outstanding_debt_hint, { count: number(kpi.count) });
        }

        const deltaEl = tile.querySelector('[data-kpi-delta]');
        deltaEl.className = 'sa-kpi-delta';
        if (key === 'outstanding_debt') {
            deltaEl.hidden = true;
            return;
        }
        deltaEl.hidden = false;

        if (kpi.previous === null || kpi.value === null) {
            deltaEl.textContent = i18n.no_previous;
            return;
        }
        const diff = kpi.value - kpi.previous;
        if (diff === 0) {
            deltaEl.innerHTML = `<span class="material-symbols-outlined" aria-hidden="true">trending_flat</span>${escapeHtml(i18n.unchanged)}`;
            return;
        }
        if (key !== 'collection_rate' && kpi.previous === 0) {
            deltaEl.textContent = i18n.no_previous;
            return;
        }
        // Rates compare in percentage points; counts and amounts in relative %.
        const change = key === 'collection_rate' ? diff : (diff / kpi.previous) * 100;
        const magnitude = `${change > 0 ? '+' : '−'}${number(Math.abs(change), 1)}`;
        const label = key === 'collection_rate' ? trans(i18n.percentage_points, { value: magnitude }) : `${magnitude}%`;
        const worse = HIGHER_IS_WORSE.has(key) ? diff > 0 : diff < 0;
        deltaEl.classList.add(worse ? 'is-bad' : 'is-good');
        deltaEl.innerHTML = `<span class="material-symbols-outlined" aria-hidden="true">${diff > 0 ? 'trending_up' : 'trending_down'}</span>${escapeHtml(trans(i18n.vs_previous, { delta: label }))}`;
    });
}

/**
 * Single-series line chart with gridlines, sparse x labels and a crosshair tooltip. Null values
 * break the line (shown as gaps, never as zero).
 *
 * @param {HTMLElement} container Chart container (receives the SVG and tooltip).
 * @param {{axis: string, title: string, value: number|null, extra?: string}[]} points Ordered points.
 * @param {{format: (v: number) => string, axisFormat?: (v: number) => string, integer?: boolean, height?: number}} options
 */
function renderLineChart(container, points, { format, axisFormat = format, integer = false, height = CHART_HEIGHT }) {
    container.replaceChildren();
    const known = points.map(point => point.value).filter(value => value !== null);
    const width = Math.max(container.clientWidth, 240);
    const plotWidth = width - CHART_PADDING.left - CHART_PADDING.right;
    const plotHeight = height - CHART_PADDING.top - CHART_PADDING.bottom;
    const ticks = height < 180 ? 2 : 4;
    const yMax = niceMax(Math.max(0, ...known), ticks, integer);
    const x = index => CHART_PADDING.left + (points.length === 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
    const y = value => CHART_PADDING.top + plotHeight - (value / yMax) * plotHeight;

    const chart = svg('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'sa-chart-svg', 'aria-hidden': 'true' });

    for (let i = 0; i <= ticks; i++) {
        const value = (yMax / ticks) * i;
        const lineY = y(value);
        chart.append(svg('line', { x1: CHART_PADDING.left, x2: width - CHART_PADDING.right, y1: lineY, y2: lineY, class: i === 0 ? 'sa-chart-baseline' : 'sa-chart-grid' }));
        const label = svg('text', { x: CHART_PADDING.left - 8, y: lineY + 3, 'text-anchor': 'end', class: 'sa-chart-axis' });
        label.textContent = axisFormat(value);
        chart.append(label);
    }

    const labelEvery = Math.ceil(points.length / Math.max(2, Math.floor(plotWidth / 70)));
    let lastLabel = null;
    points.forEach((point, index) => {
        if ((points.length - 1 - index) % labelEvery !== 0 || point.axis === lastLabel) return;
        lastLabel = point.axis;
        const label = svg('text', { x: x(index), y: height - 8, 'text-anchor': 'middle', class: 'sa-chart-axis' });
        label.textContent = point.axis;
        chart.append(label);
    });

    // Split into runs of known values so missing readings render as gaps.
    const runs = [];
    let run = [];
    points.forEach((point, index) => {
        if (point.value === null) {
            if (run.length) runs.push(run);
            run = [];
            return;
        }
        run.push([x(index), y(point.value)]);
    });
    if (run.length) runs.push(run);
    runs.forEach(segment => {
        const coords = segment.map(([px, py]) => `${px},${py}`);
        const baseline = y(0);
        chart.append(svg('path', {
            d: `M${coords.join(' L')} L${segment[segment.length - 1][0]},${baseline} L${segment[0][0]},${baseline} Z`,
            class: 'sa-chart-area',
        }));
        if (segment.length === 1) {
            chart.append(svg('circle', { cx: segment[0][0], cy: segment[0][1], r: 3, class: 'sa-chart-marker' }));
        } else {
            chart.append(svg('polyline', { points: coords.join(' '), class: 'sa-chart-line' }));
        }
    });

    const crosshair = svg('line', { y1: CHART_PADDING.top, y2: CHART_PADDING.top + plotHeight, class: 'sa-chart-crosshair', visibility: 'hidden' });
    const marker = svg('circle', { r: 5, class: 'sa-chart-marker', visibility: 'hidden' });
    chart.append(crosshair, marker);

    const tooltip = document.createElement('div');
    tooltip.className = 'sa-chart-tooltip';
    tooltip.hidden = true;

    const show = index => {
        const point = points[index];
        const px = x(index);
        const py = point.value === null ? CHART_PADDING.top + plotHeight : y(point.value);
        crosshair.setAttribute('x1', px);
        crosshair.setAttribute('x2', px);
        crosshair.setAttribute('visibility', 'visible');
        marker.setAttribute('cx', px);
        marker.setAttribute('cy', py);
        marker.setAttribute('visibility', point.value === null ? 'hidden' : 'visible');
        tooltip.innerHTML = `<span>${escapeHtml(point.title)}</span>`
            + `<strong>${escapeHtml(point.value === null ? '—' : format(point.value))}</strong>`
            + (point.extra ? `<small>${escapeHtml(point.extra)}</small>` : '');
        tooltip.hidden = false;
        const left = Math.min(Math.max(px - tooltip.offsetWidth / 2, 0), width - tooltip.offsetWidth);
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${Math.max(py - tooltip.offsetHeight - 12, 0)}px`;
    };
    const hide = () => {
        crosshair.setAttribute('visibility', 'hidden');
        marker.setAttribute('visibility', 'hidden');
        tooltip.hidden = true;
    };

    // One hit column per point, wider than the mark, so the nearest point is always picked.
    const columnWidth = points.length > 1 ? plotWidth / (points.length - 1) : plotWidth;
    points.forEach((_, index) => {
        const hit = svg('rect', { x: x(index) - columnWidth / 2, y: CHART_PADDING.top, width: columnWidth, height: plotHeight, class: 'sa-chart-hit' });
        hit.addEventListener('pointerenter', () => show(index));
        chart.append(hit);
    });
    chart.addEventListener('pointerleave', hide);

    container.append(chart, tooltip);
}

/** Daily orders/GMV trend on the shared line chart. */
function renderTrend(container, daily, metric, i18n, days) {
    const formatValue = metric === 'gmv' ? money : value => number(value);
    const total = daily.reduce((sum, point) => sum + point[metric], 0);
    document.querySelector('#sa-trend-total').textContent = trans(i18n.trend_total, { days, value: formatValue(total) });

    if (total === 0) {
        container.replaceChildren();
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.trend_empty, { days }))}</p>`;
        return;
    }

    renderLineChart(container, daily.map(point => ({
        axis: shortDay(point.date),
        title: longDay(point.date),
        value: point[metric],
        extra: metric === 'gmv' ? `${i18n.metric_orders}: ${number(point.orders)}` : `${i18n.metric_gmv}: ${money(point.gmv)}`,
    })), {
        format: formatValue,
        axisFormat: metric === 'gmv' ? compactMoney : value => number(value),
        integer: metric === 'orders',
    });
}

function renderTrendTable(tbody, daily) {
    tbody.innerHTML = [...daily].reverse().map(point => `<tr><td class="col-date">${escapeHtml(longDay(point.date))}</td>`
        + `<td class="num">${escapeHtml(number(point.orders))}</td><td class="num">${escapeHtml(money(point.gmv))}</td></tr>`).join('');
}

/** Horizontal bars, one per age bucket, on an ordinal blue ramp (older = darker) with direct value labels. */
function renderAging(container, aging, i18n) {
    const total = aging.reduce((sum, bucket) => sum + bucket.amount, 0);
    if (total === 0) {
        container.innerHTML = `<p class="sa-empty">${escapeHtml(i18n.aging_empty)}</p>`;
        return;
    }
    const max = Math.max(...aging.map(bucket => bucket.amount));
    container.innerHTML = AGING_BUCKETS.map((key, index) => {
        const bucket = aging.find(item => item.bucket === key) || { amount: 0, count: 0 };
        const width = bucket.amount > 0 ? Math.max((bucket.amount / max) * 100, 1.5) : 0;
        const share = number((bucket.amount / total) * 100, 1);
        const detail = `${money(bucket.amount)} · ${trans(i18n.aging_count, { count: number(bucket.count) })} · ${share}%`;
        return `<div class="sa-aging-row" title="${escapeHtml(`${i18n[`aging_${key}`]}: ${detail}`)}">`
            + `<span class="sa-aging-label">${escapeHtml(i18n[`aging_${key}`])}</span>`
            + `<span class="sa-aging-track"><span class="sa-aging-bar step-${index + 1}" style="width:${width}%"></span></span>`
            + `<span class="sa-aging-value"><strong>${escapeHtml(money(bucket.amount))}</strong>`
            + `<small>${escapeHtml(trans(i18n.aging_count, { count: number(bucket.count) }))}</small></span></div>`;
    }).join('');
}

function renderStuck(tbody, campaigns, i18n, campaignsUrl) {
    if (!campaigns.length) {
        emptyRow(tbody, 7, 'task_alt', i18n.stuck_empty);
        return;
    }
    tbody.innerHTML = campaigns.map(campaign => {
        const overdue = campaign.overdue_hours >= 48
            ? trans(i18n.overdue_days, { days: Math.floor(campaign.overdue_hours / 24) })
            : trans(i18n.overdue_hours, { hours: campaign.overdue_hours });
        const url = `${campaignsUrl}?q=${encodeURIComponent(campaign.name)}`;
        return `<tr><td><strong>${escapeHtml(campaign.name)}</strong><br><small class="sa-muted">${escapeHtml(campaign.code || '')} · ${escapeHtml(campaign.restaurant)}</small></td>`
            + `<td>${escapeHtml(campaign.room || '—')}</td><td class="col-status">${statusPill(campaign.status)}</td>`
            + `<td class="col-date">${escapeHtml(dateTime(campaign.deadline))}</td>`
            + `<td class="col-date"><span class="sa-overdue"><span class="material-symbols-outlined" aria-hidden="true">schedule</span>${escapeHtml(overdue)}</span></td>`
            + `<td class="num">${escapeHtml(number(campaign.orders))}</td>`
            + `<td class="col-actions"><a class="sa-button secondary" href="${escapeHtml(url)}">${escapeHtml(i18n.handle)}</a></td></tr>`;
    }).join('');
}

const FLAG_ICONS = { dormant: 'bedtime', bad_debt: 'money_off', no_admin: 'person_off', high_cancel: 'block' };

function renderRooms(tbody, rooms, i18n, roomUrl, thresholds) {
    if (!rooms.length) {
        emptyRow(tbody, 9, 'meeting_room', i18n.rooms_empty);
        return;
    }
    const flagHint = flag => trans(i18n[`flag_${flag}_hint`], {
        days: flag === 'dormant' ? thresholds.dormant_days : thresholds.overdue_days,
        percent: flag === 'high_cancel' ? thresholds.high_cancel_percent : thresholds.bad_debt_percent,
    });

    tbody.innerHTML = rooms.map(room => {
        const flags = room.flags.length
            ? room.flags.map(flag => `<span class="sa-flag sa-flag-${escapeHtml(flag)}" title="${escapeHtml(flagHint(flag))}">`
                + `<span class="material-symbols-outlined" aria-hidden="true">${FLAG_ICONS[flag] || 'warning'}</span>${escapeHtml(i18n[`flag_${flag}`] || flag)}</span>`).join('')
            : `<span class="sa-flag sa-flag-ok"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>${escapeHtml(i18n.flag_ok)}</span>`;
        const cancelled = room.campaigns_cancelled > 0
            ? `<br><small class="sa-muted">${escapeHtml(trans(i18n.campaigns_cancelled, { count: number(room.campaigns_cancelled) }))}</small>`
            : '';
        const overdue = room.debt_outstanding > 0
            ? `${escapeHtml(money(room.debt_overdue))}<br><small class="sa-muted">${escapeHtml(number(room.debt_overdue_ratio, 1))}%</small>`
            : '—';
        return `<tr><td><a class="sa-link" href="${escapeHtml(roomUrl.replace('__SLUG__', encodeURIComponent(room.slug)))}"><strong>${escapeHtml(room.name)}</strong></a>`
            + `<br>${statusPill(room.status)}</td>`
            + `<td class="num">${escapeHtml(trans(i18n.members_value, { active: number(room.members_active), total: number(room.members_total) }))}</td>`
            + `<td class="num">${escapeHtml(number(room.campaigns))}${cancelled}</td>`
            + `<td class="num">${escapeHtml(number(room.orders))}</td>`
            + `<td class="num">${escapeHtml(money(room.gmv))}</td>`
            + `<td class="num">${room.debt_outstanding > 0 ? escapeHtml(money(room.debt_outstanding)) : '—'}</td>`
            + `<td class="num">${overdue}</td>`
            + `<td class="col-date">${room.last_activity_at ? escapeHtml(dateTime(room.last_activity_at)) : `<span class="sa-muted">${escapeHtml(i18n.never_active)}</span>`}</td>`
            + `<td><div class="sa-flags">${flags}</div></td></tr>`;
    }).join('');
}

const SEVERITIES = ['low', 'medium', 'high'];
const SEGMENT_GAP = 2;

/** Rectangle path with only the top corners rounded (data end of a vertical bar). */
function topRoundedRect(x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height);
    return `M${x},${y + height} V${y + r} Q${x},${y} ${x + r},${y} H${x + width - r} Q${x + width},${y} ${x + width},${y + r} V${y + height} Z`;
}

/**
 * Stacked daily bars of security events by severity (low at the baseline, high on top) with a per-bar tooltip.
 */
function renderSecurityChart(container, daily, i18n, days) {
    container.replaceChildren();
    const totals = daily.map(day => SEVERITIES.reduce((sum, severity) => sum + day[severity], 0));
    if (totals.every(total => total === 0)) {
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.security_empty, { days }))}</p>`;
        return;
    }

    const width = Math.max(container.clientWidth, 280);
    const plotWidth = width - CHART_PADDING.left - CHART_PADDING.right;
    const plotHeight = CHART_HEIGHT - CHART_PADDING.top - CHART_PADDING.bottom;
    const ticks = 4;
    const yMax = niceMax(Math.max(...totals), ticks, true);
    const slot = plotWidth / daily.length;
    const barWidth = Math.max(Math.min(slot * 0.6, 28), 4);
    const y = value => CHART_PADDING.top + plotHeight - (value / yMax) * plotHeight;

    const chart = svg('svg', { viewBox: `0 0 ${width} ${CHART_HEIGHT}`, width, height: CHART_HEIGHT, class: 'sa-chart-svg', 'aria-hidden': 'true' });
    for (let i = 0; i <= ticks; i++) {
        const value = (yMax / ticks) * i;
        chart.append(svg('line', { x1: CHART_PADDING.left, x2: width - CHART_PADDING.right, y1: y(value), y2: y(value), class: i === 0 ? 'sa-chart-baseline' : 'sa-chart-grid' }));
        const label = svg('text', { x: CHART_PADDING.left - 8, y: y(value) + 3, 'text-anchor': 'end', class: 'sa-chart-axis' });
        label.textContent = number(value);
        chart.append(label);
    }

    const labelEvery = Math.ceil(daily.length / Math.max(2, Math.floor(plotWidth / 60)));
    const tooltip = document.createElement('div');
    tooltip.className = 'sa-chart-tooltip';
    tooltip.hidden = true;

    daily.forEach((day, index) => {
        const centerX = CHART_PADDING.left + slot * index + slot / 2;
        const barX = centerX - barWidth / 2;
        const group = svg('g', { class: 'sa-stack' });
        let base = 0;
        const present = SEVERITIES.filter(severity => day[severity] > 0);
        present.forEach((severity, position) => {
            const top = y(base + day[severity]);
            // 2px surface gap between stacked segments, taken from the lower edge of each upper segment.
            const bottom = y(base) - (position > 0 ? SEGMENT_GAP : 0);
            const height = Math.max(bottom - top, 1);
            const isTop = position === present.length - 1;
            group.append(isTop
                ? svg('path', { d: topRoundedRect(barX, top, barWidth, height, 4), class: `sa-sev sev-${severity}` })
                : svg('rect', { x: barX, y: top, width: barWidth, height, class: `sa-sev sev-${severity}` }));
            base += day[severity];
        });
        chart.append(group);

        if ((daily.length - 1 - index) % labelEvery === 0) {
            const label = svg('text', { x: centerX, y: CHART_HEIGHT - 8, 'text-anchor': 'middle', class: 'sa-chart-axis' });
            label.textContent = shortDay(day.date);
            chart.append(label);
        }

        const hit = svg('rect', { x: CHART_PADDING.left + slot * index, y: CHART_PADDING.top, width: slot, height: plotHeight, class: 'sa-chart-hit' });
        hit.addEventListener('pointerenter', () => {
            chart.querySelectorAll('.sa-stack').forEach((stack, stackIndex) => stack.classList.toggle('is-dimmed', stackIndex !== index));
            tooltip.innerHTML = `<span>${escapeHtml(longDay(day.date))}</span>`
                + `<strong>${escapeHtml(trans(i18n.events_count, { count: number(totals[index]) }))}</strong>`
                + [...SEVERITIES].reverse().map(severity => `<small class="sa-tooltip-row"><i class="sa-swatch sev-${severity}"></i>${escapeHtml(i18n[`sev_${severity}`])}: ${escapeHtml(number(day[severity]))}</small>`).join('');
            tooltip.hidden = false;
            const left = Math.min(Math.max(centerX - tooltip.offsetWidth / 2, 0), width - tooltip.offsetWidth);
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${Math.max(y(totals[index]) - tooltip.offsetHeight - 10, 0)}px`;
        });
        chart.append(hit);
    });
    chart.addEventListener('pointerleave', () => {
        chart.querySelectorAll('.sa-stack').forEach(stack => stack.classList.remove('is-dimmed'));
        tooltip.hidden = true;
    });

    container.append(chart, tooltip);
}

function renderSecurityTable(tbody, daily) {
    tbody.innerHTML = [...daily].reverse().map(day => `<tr><td class="col-date">${escapeHtml(longDay(day.date))}</td>`
        + SEVERITIES.map(severity => `<td class="num">${escapeHtml(number(day[severity]))}</td>`).join('') + '</tr>').join('');
}

/** Horizontal bars of the most frequent security event types, direct-labeled with their counts. */
function renderSecurityTypes(container, types, typeLabels, i18n, days) {
    if (!types.length) {
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.security_empty, { days }))}</p>`;
        return;
    }
    const max = Math.max(...types.map(item => item.count));
    container.innerHTML = types.map(item => {
        const label = typeLabels[item.type] || item.type;
        return `<div class="sa-aging-row sa-type-row" title="${escapeHtml(`${label}: ${number(item.count)}`)}">`
            + `<span class="sa-aging-label">${escapeHtml(label)}</span>`
            + `<span class="sa-aging-track"><span class="sa-aging-bar step-3" style="width:${Math.max((item.count / max) * 100, 1.5)}%"></span></span>`
            + `<span class="sa-aging-value"><strong>${escapeHtml(number(item.count))}</strong></span></div>`;
    }).join('');
}

function renderIps(tbody, ips, i18n, securityUrl, hours) {
    if (!ips.length) {
        emptyRow(tbody, 7, 'verified_user', trans(i18n.ips_empty, { hours }));
        return;
    }
    tbody.innerHTML = ips.map(ip => {
        const badge = ip.suspicious
            ? `<span class="sa-flag sa-flag-bad_debt"><span class="material-symbols-outlined" aria-hidden="true">gpp_bad</span>${escapeHtml(i18n.ip_suspicious)}</span>`
            : `<span class="sa-flag sa-flag-dormant"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>${escapeHtml(i18n.ip_watch)}</span>`;
        return `<tr><td class="col-mono"><code class="sa-code">${escapeHtml(ip.ip_address)}</code></td>`
            + `<td class="num">${escapeHtml(number(ip.events))}</td>`
            + `<td class="num">${escapeHtml(number(ip.failed_logins))}</td>`
            + `<td class="num">${escapeHtml(number(ip.high_events))}</td>`
            + `<td class="col-date">${escapeHtml(dateTime(ip.last_seen_at))}</td><td class="col-status">${badge}</td>`
            + `<td class="col-actions"><a class="sa-button secondary" href="${escapeHtml(`${securityUrl}?q=${encodeURIComponent(ip.ip_address)}`)}">${escapeHtml(i18n.view_events)}</a></td></tr>`;
    }).join('');
}

const ADMIN_FLAG_ICONS = { stale: 'schedule', never_logged_in: 'no_accounts', no_rooms: 'meeting_room', inactive: 'block' };
const ADMIN_FLAG_CLASSES = { stale: 'no_admin', never_logged_in: 'no_admin', no_rooms: 'dormant', inactive: 'bad_debt' };

function renderAdmins(tbody, admins, i18n, adminUrl, staleDays) {
    if (!admins.length) {
        emptyRow(tbody, 7, 'manage_accounts', i18n.admins_empty);
        return;
    }
    tbody.innerHTML = admins.map(admin => {
        const flags = admin.flags.length
            ? admin.flags.map(flag => `<span class="sa-flag sa-flag-${ADMIN_FLAG_CLASSES[flag] || 'dormant'}" title="${escapeHtml(trans(i18n[`admin_flag_${flag}_hint`], { days: staleDays }))}">`
                + `<span class="material-symbols-outlined" aria-hidden="true">${ADMIN_FLAG_ICONS[flag] || 'warning'}</span>${escapeHtml(i18n[`admin_flag_${flag}`] || flag)}</span>`).join('')
            : `<span class="sa-flag sa-flag-ok"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>${escapeHtml(i18n.flag_ok)}</span>`;
        return `<tr><td><a class="sa-link" href="${escapeHtml(adminUrl.replace('__ID__', encodeURIComponent(admin.id)))}"><strong>${escapeHtml(admin.name)}</strong></a>`
            + `<br><small class="sa-muted">${sensitiveHtml(admin.email, admin.email_reveal)}</small></td>`
            + `<td class="num">${escapeHtml(number(admin.rooms))}</td>`
            + `<td class="num">${escapeHtml(number(admin.campaigns_created))}</td>`
            + `<td class="num">${escapeHtml(number(admin.payments_confirmed))}</td>`
            + `<td class="num">${escapeHtml(number(admin.audit_actions))}</td>`
            + `<td class="col-date">${admin.last_login_at ? escapeHtml(dateTime(admin.last_login_at)) : `<span class="sa-muted">${escapeHtml(i18n.never_logged_in)}</span>`}</td>`
            + `<td><div class="sa-flags">${flags}</div></td></tr>`;
    }).join('');
}

const HEATMAP_LEVELS = 5;

/** Weekday × hour grid on a single-hue sequential ramp; empty cells stay neutral. Each cell carries its own tooltip text. */
function renderHeatmap(container, heatmap, i18n) {
    const peakEl = document.querySelector('#sa-heatmap-peak');
    peakEl.textContent = '';
    if (!heatmap.total) {
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.heatmap_empty, { days: heatmap.days }))}</p>`;
        return;
    }
    const weekdays = i18n.weekdays || [];
    const weekdaysLong = i18n.weekdays_long || weekdays;
    const hourText = hour => String(hour % 24).padStart(2, '0');
    const cellLabel = (day, hour, count) => trans(i18n.heatmap_cell, {
        day: weekdaysLong[day], hour: hourText(hour), next: hourText(hour + 1), count: number(count),
    });

    let peak = { day: 0, hour: 0, count: -1 };
    const rows = heatmap.cells.map((hours, day) => {
        const cells = hours.map((count, hour) => {
            if (count > peak.count) peak = { day, hour, count };
            const level = count > 0 ? Math.max(1, Math.ceil((count / heatmap.max) * HEATMAP_LEVELS)) : 0;
            const label = cellLabel(day, hour, count);
            return `<span class="sa-heat-cell level-${level}" role="gridcell" title="${escapeHtml(label)}" aria-label="${escapeHtml(label)}"></span>`;
        }).join('');
        return `<div class="sa-heat-row" role="row"><span class="sa-heat-day" role="rowheader">${escapeHtml(weekdays[day] || '')}</span>${cells}</div>`;
    }).join('');
    const hourLabels = Array.from({ length: 24 }, (_, hour) => `<span class="sa-heat-hour">${hour % 3 === 0 ? hourText(hour) : ''}</span>`).join('');
    const legend = Array.from({ length: HEATMAP_LEVELS }, (_, index) => `<span class="sa-heat-cell level-${index + 1}"></span>`).join('');

    container.innerHTML = `<div class="sa-heatmap" role="grid">${rows}<div class="sa-heat-row sa-heat-axis" aria-hidden="true"><span class="sa-heat-day"></span>${hourLabels}</div></div>`
        + `<div class="sa-heat-legend" aria-hidden="true"><span>${escapeHtml(i18n.heatmap_less)}</span>${legend}<span>${escapeHtml(i18n.heatmap_more)}</span></div>`;
    peakEl.textContent = trans(i18n.heatmap_peak, {
        day: weekdaysLong[peak.day], hour: hourText(peak.hour), next: hourText(peak.hour + 1), count: number(peak.count),
    });
}

const SMALL_CHART_HEIGHT = 150;
const PERCENT_LEVELS = 5;

/** "2026-09" → localized "Sep 2026". */
const monthLabel = month => formatDate(`${month}-01T00:00:00`, { month: 'short', year: 'numeric' });

/** Binary byte size with one decimal (e.g. "1.2 GB"). */
function bytes(value) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = Number(value) || 0;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }
    return `${number(size, unit === 0 ? 0 : 1)} ${units[unit]}`;
}

/** Cohort grid: one row per signup month, one cell per month since signup, shaded by retention rate. */
function renderCohorts(tbody, cohorts, i18n, months) {
    const columns = months + 2;
    if (!cohorts.some(cohort => cohort.size > 0)) {
        emptyRow(tbody, columns, 'group_add', trans(i18n.cohort_empty, { months }));
        return;
    }
    tbody.innerHTML = [...cohorts].reverse().map(cohort => {
        const cells = Array.from({ length: months }, (_, offset) => {
            const cell = cohort.retention.find(item => item.offset === offset);
            if (!cell || cohort.size === 0) return '<td class="sa-cohort-cell"></td>';
            const level = cell.rate > 0 ? Math.max(1, Math.ceil((cell.rate / 100) * PERCENT_LEVELS)) : 0;
            const label = trans(i18n.cohort_cell, { active: number(cell.active), size: number(cohort.size), rate: number(cell.rate, 1) });
            return `<td class="sa-cohort-cell"><span class="sa-heat-cell level-${level}" title="${escapeHtml(label)}" aria-label="${escapeHtml(label)}">`
                + `${escapeHtml(number(cell.rate, 0))}%</span></td>`;
        }).join('');
        return `<tr><th scope="row">${escapeHtml(monthLabel(cohort.month))}</th><td class="num">${escapeHtml(number(cohort.size))}</td>${cells}</tr>`;
    }).join('');
}

/** Monthly average bars (out of 5) followed by the 5→1 star distribution, both direct-labeled. */
function renderFeedback(container, feedback, i18n, months) {
    const summary = document.querySelector('#sa-feedback-summary');
    if (!feedback.count) {
        summary.textContent = '';
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.feedback_empty, { months }))}</p>`;
        return;
    }
    summary.textContent = trans(i18n.feedback_summary, { average: number(feedback.average, 2), count: number(feedback.count) });

    const monthly = feedback.monthly.map(item => {
        const width = item.average === null ? 0 : (item.average / 5) * 100;
        const value = item.average === null
            ? `<small>${escapeHtml(i18n.no_ratings)}</small>`
            : `<strong>${escapeHtml(number(item.average, 1))}/5</strong><small>${escapeHtml(trans(i18n.ratings_count, { count: number(item.count) }))}</small>`;
        return `<div class="sa-aging-row"><span class="sa-aging-label">${escapeHtml(monthLabel(item.month))}</span>`
            + `<span class="sa-aging-track"><span class="sa-aging-bar step-3" style="width:${width}%"></span></span>`
            + `<span class="sa-aging-value">${value}</span></div>`;
    }).join('');

    const max = Math.max(...Object.values(feedback.distribution));
    const distribution = [5, 4, 3, 2, 1].map(rating => {
        const count = feedback.distribution[rating] || 0;
        const width = count > 0 ? Math.max((count / max) * 100, 1.5) : 0;
        return `<div class="sa-aging-row"><span class="sa-aging-label">${escapeHtml(trans(i18n.stars, { count: rating }))}</span>`
            + `<span class="sa-aging-track"><span class="sa-aging-bar step-2" style="width:${width}%"></span></span>`
            + `<span class="sa-aging-value"><strong>${escapeHtml(number(count))}</strong></span></div>`;
    }).join('');

    container.innerHTML = `<h3>${escapeHtml(i18n.feedback_monthly)}</h3><div class="sa-aging">${monthly}</div>`
        + `<h3>${escapeHtml(i18n.feedback_distribution)}</h3><div class="sa-aging">${distribution}</div>`;
}

function renderContactTopics(container, topics, topicLabels, i18n, days) {
    const max = Math.max(0, ...topics.map(item => item.count));
    if (max === 0) {
        container.innerHTML = `<p class="sa-empty">${escapeHtml(trans(i18n.contact_empty, { days }))}</p>`;
        return;
    }
    container.innerHTML = topics.map(item => {
        const label = topicLabels[item.topic] || item.topic;
        const width = item.count > 0 ? Math.max((item.count / max) * 100, 1.5) : 0;
        return `<div class="sa-aging-row sa-type-row" title="${escapeHtml(`${label}: ${number(item.count)}`)}">`
            + `<span class="sa-aging-label">${escapeHtml(label)}</span>`
            + `<span class="sa-aging-track"><span class="sa-aging-bar step-3" style="width:${width}%"></span></span>`
            + `<span class="sa-aging-value"><strong>${escapeHtml(number(item.count))}</strong></span></div>`;
    }).join('');
}

const INFRA_CHARTS = {
    failed_jobs: { field: 'failed_jobs', format: value => number(value), integer: true },
    pending_jobs: { field: 'pending_jobs', format: value => number(value), integer: true },
    socket: { field: 'socket_connections', format: value => number(value), integer: true },
    storage: { field: 'storage_used_bytes', format: bytes, integer: false },
};

/** Uptime ratios, storage forecast and four small-multiple history charts (one metric each, shared time axis). */
function renderInfra(root, history, i18n) {
    const percent = value => (value === null ? i18n.not_measured : `${number(value, 1)}%`);
    root.querySelector('#sa-infra-db').textContent = percent(history.database_uptime);
    root.querySelector('#sa-infra-socket').textContent = percent(history.socket_uptime);
    const hasStorage = history.points.some(point => point.storage_used_bytes !== null);
    root.querySelector('#sa-infra-storage').textContent = history.storage_days_left !== null
        ? trans(i18n.storage_days_left, { days: number(history.storage_days_left) })
        : (hasStorage ? i18n.storage_stable : i18n.not_measured);

    const empty = history.points.length === 0;
    root.querySelector('#sa-infra-empty').hidden = !empty;
    root.querySelector('#sa-infra-charts').hidden = empty;
    if (empty) return;

    root.querySelectorAll('[data-infra-chart]').forEach(container => {
        const config = INFRA_CHARTS[container.dataset.infraChart];
        const points = history.points.map(point => ({
            axis: formatDate(point.at, { day: '2-digit', month: '2-digit' }),
            title: dateTime(point.at),
            value: point[config.field],
            extra: config.field === 'storage_used_bytes' && point.storage_total_bytes ? `/ ${bytes(point.storage_total_bytes)}` : undefined,
        }));
        if (points.every(point => point.value === null)) {
            container.innerHTML = `<p class="sa-empty">${escapeHtml(i18n.not_measured)}</p>`;
            return;
        }
        renderLineChart(container, points, { format: config.format, integer: config.integer, height: SMALL_CHART_HEIGHT });
    });
}

/** Parse a JSON data-* attribute, falling back to an empty object (the page still renders numbers). */
const parseData = (element, key) => {
    try {
        return JSON.parse(element.dataset[key] || '{}');
    } catch {
        return {};
    }
};

export function initSuperadminDashboard() {
    const root = document.querySelector('#sa-analytics');
    if (!root) return;

    const i18n = parseData(root, 'i18n');
    const thresholds = parseData(root, 'thresholds');
    const insightThresholds = parseData(root, 'insightThresholds');
    const securityTypes = parseData(root, 'securityTypes');
    const contactTopics = parseData(root, 'contactTopics');

    const chart = root.querySelector('#sa-trend-chart');
    const securityChart = root.querySelector('#sa-security-chart');
    const notice = root.querySelector('#sa-analytics-notice');
    const insightsNotice = root.querySelector('#sa-insights-notice');
    const trendsNotice = root.querySelector('#sa-trends-notice');
    const refreshButton = root.querySelector('[data-analytics-refresh]');
    let data = null;
    let insights = null;
    let trends = null;
    let metric = 'orders';

    const drawTrend = () => data && renderTrend(chart, data.daily, metric, i18n, data.period_days);
    const drawSecurity = () => insights && renderSecurityChart(securityChart, insights.security.daily, i18n, insightThresholds.security_days);
    const drawInfra = () => trends && renderInfra(root, trends.system_history, i18n);

    const showError = (element, error) => {
        element.textContent = `${i18n.load_failed || ''} ${error.message}`.trim();
        element.classList.add('is-visible');
    };

    const loadAnalytics = async fresh => {
        notice.classList.remove('is-visible');
        try {
            ({ data } = await dfApi(`${root.dataset.url}${fresh ? '?fresh=1' : ''}`));
            renderKpis(root, data.kpis, i18n);
            drawTrend();
            renderTrendTable(root.querySelector('#sa-trend-table'), data.daily);
            renderAging(root.querySelector('#sa-aging'), data.debt_aging, i18n);
            renderStuck(root.querySelector('#sa-stuck-body'), data.stuck_campaigns, i18n, root.dataset.campaignsUrl);
            renderRooms(root.querySelector('#sa-rooms-body'), data.rooms, i18n, root.dataset.roomUrl, thresholds);
            root.querySelector('#sa-analytics-updated').textContent = trans(i18n.updated_at, { time: dateTime(data.generated_at) });
        } catch (error) {
            showError(notice, error);
        }
    };

    const loadInsights = async fresh => {
        insightsNotice.classList.remove('is-visible');
        try {
            ({ data: insights } = await dfApi(`${root.dataset.insightsUrl}${fresh ? '?fresh=1' : ''}`));
            renderHeatmap(root.querySelector('#sa-heatmap'), insights.heatmap, i18n);
            drawSecurity();
            renderSecurityTable(root.querySelector('#sa-security-table'), insights.security.daily);
            renderSecurityTypes(root.querySelector('#sa-security-types'), insights.security.types, securityTypes, i18n, insightThresholds.activity_days);
            renderIps(root.querySelector('#sa-ips-body'), insights.security.top_ips, i18n, root.dataset.securityUrl, insightThresholds.ip_hours);
            renderAdmins(root.querySelector('#sa-admins-body'), insights.admins, i18n, root.dataset.adminUrl, insightThresholds.stale_days);
        } catch (error) {
            showError(insightsNotice, error);
        }
    };

    const loadTrends = async fresh => {
        trendsNotice.classList.remove('is-visible');
        try {
            ({ data: trends } = await dfApi(`${root.dataset.trendsUrl}${fresh ? '?fresh=1' : ''}`));
            renderCohorts(root.querySelector('#sa-cohort-body'), trends.cohorts, i18n, trends.cohorts.length);
            renderFeedback(root.querySelector('#sa-feedback'), trends.feedback, i18n, trends.feedback.monthly.length);
            renderContactTopics(root.querySelector('#sa-contact-topics'), trends.contact_topics, contactTopics, i18n, insightThresholds.contact_days);
            drawInfra();
        } catch (error) {
            showError(trendsNotice, error);
        }
    };

    // A restricted Superadmin only gets the operations figures of its own rooms (security / trends are platform-wide).
    const platform = root.dataset.platform !== '0';
    const load = async (fresh = false) => {
        refreshButton.disabled = true;
        await Promise.all(platform ? [loadAnalytics(fresh), loadInsights(fresh), loadTrends(fresh)] : [loadAnalytics(fresh)]);
        refreshButton.disabled = false;
    };

    root.querySelectorAll('[data-trend-metric]').forEach(button => {
        button.addEventListener('click', () => {
            metric = button.dataset.trendMetric;
            root.querySelectorAll('[data-trend-metric]').forEach(other => {
                const active = other === button;
                other.classList.toggle('is-active', active);
                other.setAttribute('aria-pressed', String(active));
            });
            drawTrend();
        });
    });
    refreshButton.addEventListener('click', () => load(true));

    const widths = new Map();
    const observer = new ResizeObserver(entries => entries.forEach(entry => {
        const width = entry.target.clientWidth;
        if (widths.get(entry.target) === width) return;
        widths.set(entry.target, width);
        if (entry.target === chart) drawTrend();
        else if (entry.target === securityChart) drawSecurity();
        else drawInfra();
    }));
    [chart, securityChart, ...root.querySelectorAll('[data-infra-chart]')].forEach(element => {
        widths.set(element, element.clientWidth);
        observer.observe(element);
    });

    load();
}
