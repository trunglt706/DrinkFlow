/**
 * Revenue page charts (resources/views/superadmin/billing/revenue.blade.php).
 *
 * - [data-revenue-trend]: 6-month combo on ONE VND axis — bars = amount invoiced, line = amount collected.
 * - [data-revenue-donut]: MRR share per package (slots follow --sa-series-N, the table beside it carries
 *   the exact values). Hovering a slice or a table row highlights both and shows the package in the
 *   donut's centre (no tooltip over the ring).
 *
 * Data, pre-formatted amounts and every label come from the Blade data-* attributes; nothing is
 * hard-coded here. Both charts redraw on resize and show a tooltip on hover / keyboard focus.
 */
const SVG_NS = 'http://www.w3.org/2000/svg';
const TREND_HEIGHT = 240;
const PAD = { top: 14, right: 56, bottom: 28, left: 56 };
const DONUT_SIZE = 200;
const DONUT_OUTER = 96;
const DONUT_INNER = 62;

const parse = (value, fallback) => {
    try {
        return JSON.parse(value || '');
    } catch {
        return fallback;
    }
};

const svgEl = (tag, attrs = {}, text = null) => {
    const node = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
    if (text !== null) node.textContent = text;
    return node;
};

const locale = () => document.documentElement.lang || undefined;
const compact = (value) => new Intl.NumberFormat(locale(), { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);

/** Round the axis maximum up to a 1/2/2.5/5 × 10^n step so the 4 gridlines land on readable values. */
const niceMax = (value) => {
    if (value <= 0) return 1;
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 2.5, 5, 10].find((candidate) => candidate * magnitude >= value / 4) * magnitude;
    return step * 4;
};

/** Tooltip element shared by a chart container (rows: [label, value] pairs, first row is the title). */
function tooltipFor(container) {
    let tip = container.querySelector('.sa-chart-tooltip');
    if (!tip) {
        tip = document.createElement('div');
        tip.className = 'sa-chart-tooltip';
        tip.hidden = true;
        tip.setAttribute('role', 'status');
        container.append(tip);
    }
    const show = (title, rows, x, y) => {
        tip.replaceChildren();
        const heading = document.createElement('small');
        heading.textContent = title;
        tip.append(heading);
        rows.forEach(([label, value]) => {
            const line = document.createElement('span');
            const strong = document.createElement('strong');
            strong.textContent = value;
            line.append(`${label}: `);
            tip.append(line, strong);
        });
        tip.hidden = false;
        const maxLeft = container.clientWidth - tip.offsetWidth - 4;
        tip.style.left = `${Math.max(4, Math.min(x + 12, maxLeft))}px`;
        tip.style.top = `${Math.max(0, y - tip.offsetHeight - 8)}px`;
    };
    return { show, hide: () => { tip.hidden = true; } };
}

function drawTrend(container) {
    const rows = parse(container.dataset.revenueTrend, []);
    const i18n = parse(container.dataset.i18n, {});
    if (!rows.length) return;

    const width = Math.max(320, container.clientWidth);
    const plotW = width - PAD.left - PAD.right;
    const plotH = TREND_HEIGHT - PAD.top - PAD.bottom;
    const max = niceMax(Math.max(...rows.flatMap((row) => [row.invoiced, row.collected])));
    const band = plotW / rows.length;
    const barW = Math.min(36, band * 0.46);
    const x = (index) => PAD.left + band * index + band / 2;
    const y = (value) => PAD.top + plotH - (value / max) * plotH;

    const svg = svgEl('svg', { class: 'sa-chart-svg', viewBox: `0 0 ${width} ${TREND_HEIGHT}`, 'aria-hidden': 'true' });
    const bands = svgEl('g');
    const grid = svgEl('g');
    for (let step = 0; step <= 4; step += 1) {
        const value = (max / 4) * step;
        const gy = y(value);
        grid.append(svgEl('line', { class: step === 0 ? 'sa-chart-baseline' : 'sa-chart-grid', x1: PAD.left, x2: width - PAD.right, y1: gy, y2: gy }));
        grid.append(svgEl('text', { class: 'sa-chart-axis', x: PAD.left - 8, y: gy + 4, 'text-anchor': 'end' }, compact(value)));
    }
    svg.append(bands, grid);

    const bars = svgEl('g');
    rows.forEach((row, index) => {
        bands.append(svgEl('rect', { class: 'sa-combo-band', x: PAD.left + band * index, y: PAD.top, width: band, height: plotH, 'data-band': index }));
        const top = y(row.invoiced);
        const height = PAD.top + plotH - top;
        if (height > 0.5) {
            // Rounded data end (4px) at the top, square on the baseline.
            const r = Math.min(4, barW / 2, height);
            const left = x(index) - barW / 2;
            const right = left + barW;
            const bottom = PAD.top + plotH;
            bars.append(svgEl('path', {
                class: 'sa-combo-bar',
                d: `M${left},${bottom}V${top + r}Q${left},${top} ${left + r},${top}H${right - r}Q${right},${top} ${right},${top + r}V${bottom}Z`,
            }));
        }
        svg.append(svgEl('text', { class: 'sa-chart-axis', x: x(index), y: TREND_HEIGHT - 8, 'text-anchor': 'middle' }, row.label));
    });
    svg.append(bars);

    const points = rows.map((row, index) => [x(index), y(row.collected)]);
    svg.append(svgEl('path', { class: 'sa-combo-line', d: points.map(([px, py], index) => `${index ? 'L' : 'M'}${px},${py}`).join('') }));
    points.forEach(([px, py]) => svg.append(svgEl('circle', { class: 'sa-combo-marker', cx: px, cy: py, r: 4 })));
    // Direct label at the line end (the legend above names both series).
    const [lastX, lastY] = points[points.length - 1];
    svg.append(svgEl('text', { class: 'sa-combo-label', x: lastX + 10, y: lastY + 4 }, i18n.collected || ''));

    // Hover / focus layer: one hit rect per month, larger than the marks.
    const tooltip = tooltipFor(container);
    const hits = svgEl('g');
    const activate = (index) => {
        bands.querySelectorAll('.sa-combo-band').forEach((node) => node.classList.toggle('is-active', Number(node.dataset.band) === index));
        const row = rows[index];
        const scale = container.clientWidth / width;
        tooltip.show(row.label, [[i18n.invoiced, row.invoiced_text], [i18n.collected, row.collected_text]],
            x(index) * scale, Math.min(y(row.invoiced), y(row.collected)) * scale);
    };
    const reset = () => {
        bands.querySelectorAll('.sa-combo-band').forEach((node) => node.classList.remove('is-active'));
        tooltip.hide();
    };
    rows.forEach((row, index) => {
        const hit = svgEl('rect', { class: 'sa-chart-hit', x: PAD.left + band * index, y: 0, width: band, height: TREND_HEIGHT, tabindex: 0, 'aria-label': `${row.label}: ${i18n.invoiced} ${row.invoiced_text}, ${i18n.collected} ${row.collected_text}` });
        hit.addEventListener('mouseenter', () => activate(index));
        hit.addEventListener('focus', () => activate(index));
        hit.addEventListener('mouseleave', reset);
        hit.addEventListener('blur', reset);
        hits.append(hit);
    });
    svg.append(hits);

    container.querySelector('svg')?.remove();
    container.prepend(svg);
}

function drawDonut(container) {
    const rows = parse(container.dataset.revenueDonut, []);
    const total = rows.reduce((sum, row) => sum + row.mrr, 0);
    if (!rows.length || total <= 0) return;

    const center = DONUT_SIZE / 2;
    const svg = svgEl('svg', { class: 'sa-chart-svg', viewBox: `0 0 ${DONUT_SIZE} ${DONUT_SIZE}`, 'aria-hidden': 'true' });
    const point = (angle, radius) => [center + radius * Math.sin(angle), center - radius * Math.cos(angle)];
    const table = document.querySelector('[data-revenue-package-table]');
    const centerValue = container.querySelector('.sa-donut-center strong');
    const centerLabel = container.querySelector('.sa-donut-center small');
    const totalText = [centerValue?.textContent ?? '', centerLabel?.textContent ?? ''];

    const highlight = (slot) => {
        const row = rows.find((candidate) => candidate.slot === slot) ?? null;
        container.classList.toggle('has-focus', row !== null);
        svg.querySelectorAll('.sa-donut-slice').forEach((node) => node.classList.toggle('is-active', Number(node.dataset.slot) === slot));
        table?.querySelectorAll('tr[data-package-slot]').forEach((tr) => tr.classList.toggle('is-active', Number(tr.dataset.packageSlot) === slot));
        if (centerValue && centerLabel) {
            centerValue.textContent = row ? row.mrr_text : totalText[0];
            centerLabel.textContent = row ? `${row.package} · ${row.share}%` : totalText[1];
        }
    };

    let angle = 0;
    rows.forEach((row) => {
        const sweep = (row.mrr / total) * Math.PI * 2;
        const start = angle;
        const end = angle + sweep;
        angle = end;
        let d;
        if (sweep >= Math.PI * 2 - 1e-6) {
            // A single package: two half rings (an arc cannot start and end on the same point).
            d = `M${center},${center - DONUT_OUTER}A${DONUT_OUTER},${DONUT_OUTER} 0 1 1 ${center},${center + DONUT_OUTER}A${DONUT_OUTER},${DONUT_OUTER} 0 1 1 ${center},${center - DONUT_OUTER}`
                + `M${center},${center - DONUT_INNER}A${DONUT_INNER},${DONUT_INNER} 0 1 0 ${center},${center + DONUT_INNER}A${DONUT_INNER},${DONUT_INNER} 0 1 0 ${center},${center - DONUT_INNER}Z`;
        } else {
            const large = sweep > Math.PI ? 1 : 0;
            const [ox1, oy1] = point(start, DONUT_OUTER);
            const [ox2, oy2] = point(end, DONUT_OUTER);
            const [ix2, iy2] = point(end, DONUT_INNER);
            const [ix1, iy1] = point(start, DONUT_INNER);
            d = `M${ox1},${oy1}A${DONUT_OUTER},${DONUT_OUTER} 0 ${large} 1 ${ox2},${oy2}L${ix2},${iy2}A${DONUT_INNER},${DONUT_INNER} 0 ${large} 0 ${ix1},${iy1}Z`;
        }
        const slice = svgEl('path', {
            class: 'sa-donut-slice', d, 'fill-rule': 'evenodd', 'data-slot': row.slot, tabindex: 0,
            style: `fill: var(--sa-series-${row.slot})`,
            'aria-label': `${row.package}: ${row.mrr_text} (${row.share}%)`,
        });
        const show = () => highlight(row.slot);
        slice.addEventListener('mouseenter', show);
        slice.addEventListener('focus', show);
        slice.addEventListener('mouseleave', () => highlight(null));
        slice.addEventListener('blur', () => highlight(null));
        svg.append(slice);
    });

    table?.querySelectorAll('tr[data-package-slot]').forEach((tr) => {
        tr.addEventListener('mouseenter', () => highlight(Number(tr.dataset.packageSlot)));
        tr.addEventListener('mouseleave', () => highlight(null));
    });

    container.querySelector('svg')?.remove();
    container.prepend(svg);
}

export function initSuperadminRevenueCharts() {
    const trend = document.querySelector('[data-revenue-trend]');
    const donut = document.querySelector('[data-revenue-donut]');
    if (donut) drawDonut(donut);
    if (!trend) return;
    drawTrend(trend);
    let frame = 0;
    new ResizeObserver(() => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => drawTrend(trend));
    }).observe(trend);
}
