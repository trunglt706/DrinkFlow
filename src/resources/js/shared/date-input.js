/**
 * Shared date input (Blade <x-date-input>): the field shows and accepts dd/mm/yyyy, a click anywhere in it opens a
 * calendar dropdown with "Clear" and "Today", and the value sent is kept in a hidden input as yyyy-mm-dd (the name,
 * id and classes the page and its scripts already use). Setting the hidden input's `.value` from a script updates the
 * display, and it fires `input` / `change` when the user picks a date. Texts come from the data-i18n attribute.
 */

const STYLE_ID = 'df-date-input-style';
const CSS = `
.df-date{position:relative;display:block}
.df-date-display{cursor:pointer}
.df-date-icon{position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:18px;opacity:.55}
.df-date-pop{position:fixed;z-index:1000;top:0;left:0;width:272px;padding:10px;border-radius:14px;background:#fff;color:#0f172a;
  border:1px solid rgba(15,23,42,.12);box-shadow:0 18px 40px -12px rgba(15,23,42,.35);font:500 13px/1.2 system-ui,-apple-system,'Segoe UI',sans-serif}
.df-date-pop[hidden]{display:none}
.df-date-head{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:6px}
.df-date-head strong{font-size:13px;font-weight:700}
.df-date-head select{border:1px solid rgba(15,23,42,.15);border-radius:8px;padding:3px 6px;font:600 12px system-ui,sans-serif;background:#fff;color:inherit}
.df-date-nav{width:28px;height:28px;border:0;border-radius:8px;background:transparent;color:inherit;font-size:18px;cursor:pointer}
.df-date-nav:hover,.df-date-day:hover:not(:disabled){background:rgba(0,105,72,.1)}
.df-date-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}
.df-date-dow{font-size:11px;font-weight:700;color:#64748b;padding:4px 0}
.df-date-day{height:32px;border:0;border-radius:8px;background:transparent;color:inherit;font:600 12px system-ui,sans-serif;cursor:pointer}
.df-date-day.is-out{color:#94a3b8}
.df-date-day.is-today{box-shadow:inset 0 0 0 1px var(--df-date-accent,#006948);color:var(--df-date-accent,#006948)}
.df-date-day.is-selected{background:var(--df-date-accent,#006948);color:#fff}
.df-date-day:disabled{opacity:.3;cursor:not-allowed}
.df-date-day:focus-visible,.df-date-nav:focus-visible,.df-date-foot button:focus-visible{outline:2px solid var(--df-date-accent,#006948);outline-offset:1px}
.df-date-foot{display:flex;justify-content:space-between;gap:8px;margin-top:8px;padding-top:8px;border-top:1px solid rgba(15,23,42,.08)}
.df-date-foot button{border:0;border-radius:8px;padding:6px 10px;font:700 12px system-ui,sans-serif;cursor:pointer;background:rgba(15,23,42,.06);color:inherit}
.df-date-foot [data-date-today]{background:var(--df-date-accent,#006948);color:#fff}
.df-date-error{margin-top:4px;font-size:11px;font-weight:600;color:#dc2626}
@media (prefers-color-scheme: dark){html.dark .df-date-pop{background:#1e293b;color:#e2e8f0;border-color:rgba(226,232,240,.15)}}
`;

const pad = (value) => String(value).padStart(2, '0');
const toIso = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const fromIso = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return date.getMonth() === Number(match[2]) - 1 ? date : null;
};
const display = (iso) => {
    const date = fromIso(iso);
    return date ? `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}` : '';
};
/** dd/mm/yyyy (also d/m/yyyy, dd-mm-yyyy, dd.mm.yyyy) to yyyy-mm-dd; null when not a real date. */
const parseTyped = (text) => {
    const match = /^\s*(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})\s*$/.exec(String(text || ''));
    if (!match) return null;
    const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
    return date.getDate() === Number(match[1]) && date.getMonth() === Number(match[2]) - 1 ? toIso(date) : null;
};

const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

function setup(root) {
    if (root.dataset.dateInputReady) return;
    root.dataset.dateInputReady = '1';
    let i18n = {};
    try { i18n = JSON.parse(root.dataset.i18n || '{}'); } catch { i18n = {}; }
    const months = i18n.months || [];
    const weekdays = i18n.weekdays || [];
    const field = root.querySelector('[data-date-display]');
    const hidden = root.querySelector('[data-date-value]');
    const error = root.querySelector('[data-date-error]');
    const min = fromIso(root.dataset.min);
    const max = fromIso(root.dataset.max);
    let view = fromIso(hidden.value) || new Date();
    let pop = null;

    const raw = () => valueDescriptor.get.call(hidden);
    const render = () => { field.value = display(raw()); };
    const setError = (text) => {
        if (!error) return;
        error.textContent = text || '';
        error.hidden = !text;
        field.setAttribute('aria-invalid', text ? 'true' : 'false');
    };
    // A script setting hidden.value (e.g. a preset, an edit modal) updates the display too.
    Object.defineProperty(hidden, 'value', {
        configurable: true,
        get: () => raw(),
        set: (value) => { valueDescriptor.set.call(hidden, value ?? ''); render(); },
    });
    const commit = (iso) => {
        const changed = raw() !== iso;
        valueDescriptor.set.call(hidden, iso);
        render();
        setError('');
        if (changed) {
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const outOfRange = (date) => (min && date < min) || (max && date > max);

    // The calendar floats on the page (fixed, in <body>) so no scrolling or overflow-hidden container can cut it.
    const place = () => {
        if (!pop || pop.hidden) return;
        const rect = field.getBoundingClientRect();
        const height = pop.offsetHeight;
        const below = window.innerHeight - rect.bottom;
        const top = below < height + 12 && rect.top > height + 12 ? rect.top - height - 6 : rect.bottom + 6;
        pop.style.top = `${Math.max(8, top)}px`;
        pop.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - pop.offsetWidth - 8))}px`;
    };
    const close = () => {
        if (!pop) return;
        pop.hidden = true;
        field.setAttribute('aria-expanded', 'false');
        window.removeEventListener('scroll', place, true);
        window.removeEventListener('resize', place);
    };
    const draw = () => {
        const selected = raw();
        const todayIso = toIso(new Date());
        const first = new Date(view.getFullYear(), view.getMonth(), 1);
        // Weeks start on Monday.
        const start = new Date(first);
        start.setDate(1 - ((first.getDay() + 6) % 7));
        const years = [];
        const lowYear = min ? min.getFullYear() : view.getFullYear() - 100;
        const highYear = max ? max.getFullYear() : view.getFullYear() + 10;
        for (let year = highYear; year >= lowYear; year--) years.push(year);

        pop.innerHTML = '';
        const head = document.createElement('div');
        head.className = 'df-date-head';
        const prev = Object.assign(document.createElement('button'), { type: 'button', className: 'df-date-nav', textContent: '‹' });
        prev.setAttribute('aria-label', i18n.prev || '');
        const next = Object.assign(document.createElement('button'), { type: 'button', className: 'df-date-nav', textContent: '›' });
        next.setAttribute('aria-label', i18n.next || '');
        const monthSelect = document.createElement('select');
        monthSelect.setAttribute('aria-label', i18n.month || '');
        months.forEach((name, index) => monthSelect.append(new Option(name, String(index), false, index === view.getMonth())));
        const yearSelect = document.createElement('select');
        yearSelect.setAttribute('aria-label', i18n.year || '');
        years.forEach((year) => yearSelect.append(new Option(String(year), String(year), false, year === view.getFullYear())));
        const title = document.createElement('div');
        title.style.display = 'flex';
        title.style.gap = '4px';
        title.append(monthSelect, yearSelect);
        head.append(prev, title, next);

        const grid = document.createElement('div');
        grid.className = 'df-date-grid';
        grid.setAttribute('role', 'grid');
        weekdays.forEach((name) => grid.append(Object.assign(document.createElement('span'), { className: 'df-date-dow', textContent: name })));
        for (let i = 0; i < 42; i++) {
            const day = new Date(start);
            day.setDate(start.getDate() + i);
            const iso = toIso(day);
            const button = Object.assign(document.createElement('button'), { type: 'button', className: 'df-date-day', textContent: String(day.getDate()) });
            button.dataset.date = iso;
            button.setAttribute('aria-label', display(iso));
            if (day.getMonth() !== view.getMonth()) button.classList.add('is-out');
            if (iso === todayIso) button.classList.add('is-today');
            if (iso === selected) { button.classList.add('is-selected'); button.setAttribute('aria-pressed', 'true'); }
            button.disabled = Boolean(outOfRange(day));
            grid.append(button);
        }

        const foot = document.createElement('div');
        foot.className = 'df-date-foot';
        const clear = Object.assign(document.createElement('button'), { type: 'button', textContent: i18n.clear || '' });
        clear.dataset.dateClear = '';
        const today = Object.assign(document.createElement('button'), { type: 'button', textContent: i18n.today || '' });
        today.dataset.dateToday = '';
        today.disabled = Boolean(outOfRange(new Date(new Date().toDateString())));
        foot.append(clear, today);
        pop.append(head, grid, foot);

        prev.addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); draw(); });
        next.addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); draw(); });
        monthSelect.addEventListener('change', () => { view = new Date(view.getFullYear(), Number(monthSelect.value), 1); draw(); });
        yearSelect.addEventListener('change', () => { view = new Date(Number(yearSelect.value), view.getMonth(), 1); draw(); });
        grid.addEventListener('click', (event) => {
            const button = event.target.closest('.df-date-day');
            if (!button || button.disabled) return;
            commit(button.dataset.date);
            close();
            field.focus();
        });
        clear.addEventListener('click', () => { commit(''); close(); field.focus(); });
        today.addEventListener('click', () => { commit(toIso(new Date())); close(); field.focus(); });
    };
    const open = () => {
        if (field.disabled || field.readOnly) return;
        if (!pop) {
            pop = document.createElement('div');
            pop.className = 'df-date-pop';
            pop.setAttribute('role', 'dialog');
            pop.setAttribute('aria-label', i18n.dialog || '');
            pop.addEventListener('mousedown', (event) => { if (event.target.tagName !== 'SELECT') event.preventDefault(); });
            // In <body>, outside the field: leaving its month / year selects closes it too.
            pop.addEventListener('focusout', (event) => { if (event.relatedTarget && !inside(event.relatedTarget)) close(); });
            document.body.append(pop);
        }
        view = fromIso(raw()) || (max && new Date() > max ? new Date(max) : new Date());
        draw();
        pop.hidden = false;
        field.setAttribute('aria-expanded', 'true');
        // Below the field, or above it when there is no room below; follows the page when it scrolls.
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
    };

    // Typed dates are read when the field is left or Enter is pressed.
    const readTyped = () => {
        const text = field.value.trim();
        if (text === '') { commit(''); return; }
        const iso = parseTyped(text);
        const date = fromIso(iso);
        if (!iso || outOfRange(date)) {
            setError(i18n.invalid || '');
            render();
            return;
        }
        commit(iso);
    };

    field.addEventListener('click', open);
    field.addEventListener('focus', open);
    field.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
        if (event.key === 'Enter') { event.preventDefault(); readTyped(); close(); }
        if (event.key === 'ArrowDown' && (!pop || pop.hidden)) { event.preventDefault(); open(); }
    });
    field.addEventListener('blur', () => { if (field.value !== display(raw())) readTyped(); });
    const inside = (node) => root.contains(node) || Boolean(pop?.contains(node));
    document.addEventListener('mousedown', (event) => { if (!inside(event.target)) close(); });
    // form.reset() restores the hidden value without the setter: redraw once it is done.
    root.closest('form')?.addEventListener('reset', () => setTimeout(() => { render(); setError(''); }));
    root.addEventListener('focusout', (event) => { if (event.relatedTarget && !inside(event.relatedTarget)) close(); });
    render();
}

/** Set up every date input of the page, including those added later (modals, AJAX content). */
export function initDateInputs() {
    if (!document.getElementById(STYLE_ID)) {
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = CSS;
        document.head.append(style);
    }
    document.querySelectorAll('[data-date-input]').forEach(setup);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches('[data-date-input]')) setup(node);
        node.querySelectorAll?.('[data-date-input]').forEach(setup);
    }))).observe(document.body, { childList: true, subtree: true });
}
