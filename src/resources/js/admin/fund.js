/**
 * Admin room fund page (/admin/{room}/fund): new transaction modal with browser-side checks and a formatted amount,
 * and the ledger table reloaded in place (skeleton while loading) by the type filter and the pagination.
 */
export function initAdminFund() {
    initFundModal();
    initFundLedger();
}

/** Digits of the amount field, with a leading minus kept only when allowed. */
const parseAmount = (value, allowNegative) => {
    const raw = String(value || '').trim();
    const digits = raw.replace(/\D/g, '');
    if (digits === '') return { text: allowNegative && raw.startsWith('-') ? '-' : '', number: null };
    const negative = allowNegative && raw.startsWith('-');
    const number = Number(digits) * (negative ? -1 : 1);

    return { text: (negative ? '-' : '') + Number(digits).toLocaleString('vi-VN'), number };
};

function initFundModal() {
    const modal = document.querySelector('[data-fund-modal]');
    if (!modal) return;
    let i18n = {};
    try { i18n = JSON.parse(modal.dataset.i18n || '{}'); } catch { i18n = {}; }
    const form = modal.querySelector('[data-fund-form]');
    const type = form.querySelector('[data-fund-type]');
    const amount = form.querySelector('[data-fund-amount]');
    const note = form.querySelector('[data-fund-note]');
    const submit = form.querySelector('[data-fund-submit]');
    const maxAmount = Number(form.dataset.maxAmount) || 1_000_000_000;
    const opener = document.querySelector('[data-fund-modal-open]');

    const isAdjustment = () => type.value === 'adjustment';
    const setError = (field, input, message) => {
        const error = form.querySelector(`[data-fund-error="${field}"]`);
        error.textContent = message || '';
        error.classList.toggle('hidden', !message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        input.classList.toggle('border-red-500', Boolean(message));
    };
    const formatAmount = () => {
        const caret = amount.value.length - (amount.selectionStart ?? amount.value.length);
        amount.value = parseAmount(amount.value, isAdjustment()).text;
        const position = Math.max(0, amount.value.length - caret);
        amount.setSelectionRange?.(position, position);
    };

    const validate = () => {
        let firstInvalid = null;
        const { number } = parseAmount(amount.value, isAdjustment());
        let amountError = '';
        if (number === null) amountError = i18n.amountRequired;
        else if (isAdjustment() && number === 0) amountError = i18n.amountNonZero;
        else if (!isAdjustment() && number < 1) amountError = i18n.amountPositive;
        else if (Math.abs(number) > maxAmount) amountError = i18n.amountMax;
        setError('amount', amount, amountError);
        if (amountError) firstInvalid = amount;

        const noteError = type.value !== 'deposit' && note.value.trim() === '' ? i18n.noteRequired : '';
        setError('note', note, noteError);
        if (noteError && !firstInvalid) firstInvalid = note;

        return firstInvalid;
    };

    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        type.focus();
    };
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        opener?.focus();
    };

    opener?.addEventListener('click', open);
    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-fund-modal-close]')) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    amount.addEventListener('input', () => { formatAmount(); setError('amount', amount, ''); });
    note.addEventListener('input', () => setError('note', note, ''));
    type.addEventListener('change', () => {
        amount.value = parseAmount(amount.value, isAdjustment()).text;
        setError('amount', amount, '');
        setError('note', note, '');
    });

    form.addEventListener('submit', (event) => {
        const invalid = validate();
        if (invalid) {
            event.preventDefault();
            invalid.focus();
            return;
        }
        // Only digits (and the minus of an adjustment) are sent.
        const { number } = parseAmount(amount.value, isAdjustment());
        amount.value = String(number);
        note.value = note.value.trim();
        submit.disabled = true;
    });

    // Old input after a server-side error: show it formatted, in the reopened modal.
    if (amount.value) amount.value = parseAmount(amount.value, isAdjustment()).text;
    if (modal.hasAttribute('data-open-on-load')) open();
}

function initFundLedger() {
    const section = document.querySelector('[data-fund-ledger-section]');
    if (!section) return;
    let i18n = {};
    try { i18n = JSON.parse(section.dataset.i18n || '{}'); } catch { i18n = {}; }
    const form = section.querySelector('[data-fund-filter-form]');
    const typeInput = form.querySelector('[data-fund-type-filter]');
    const reset = form.querySelector('[data-fund-filter-reset]');
    const filterError = form.querySelector('[data-fund-filter-error]');
    const ledger = section.querySelector('[data-fund-ledger]');
    const skeleton = section.querySelector('[data-fund-skeleton]');
    const pills = [...section.querySelectorAll('[data-fund-filter]')];
    const exportLink = document.querySelector('[data-fund-export]');
    const activeClasses = ['bg-primary', 'text-on-primary'];
    const idleClasses = ['bg-surface-container', 'text-on-surface-variant', 'hover:bg-surface-container-high'];
    let controller = null;

    // URL of the ledger with the filters of the form (blank filters left out).
    const formUrl = () => {
        const url = new URL(form.action, window.location.origin);
        new FormData(form).forEach((value, key) => { if (String(value).trim() !== '') url.searchParams.set(key, value); });
        return url.toString();
    };
    const syncForm = (url) => {
        const params = new URL(url, window.location.origin).searchParams;
        typeInput.value = params.get('type') || '';
        pills.forEach((pill) => {
            const active = pill.dataset.fundFilter === typeInput.value;
            pill.classList.remove(...(active ? idleClasses : activeClasses));
            pill.classList.add(...(active ? activeClasses : idleClasses));
            if (active) pill.setAttribute('aria-current', 'true'); else pill.removeAttribute('aria-current');
        });
        ['date_from', 'date_to'].forEach((name) => { form.elements[name].value = params.get(name) || ''; });
        if (!params.get('member')) window.dispatchEvent(new CustomEvent('member-combobox-reset', { detail: 'member' }));
        reset.hidden = ['type', 'member', 'date_from', 'date_to'].every((name) => !params.get(name));
    };
    const showError = (text) => {
        filterError.textContent = text || '';
        filterError.classList.toggle('hidden', !text);
    };

    const load = async (url, push = true) => {
        controller?.abort();
        controller = new AbortController();
        ledger.setAttribute('aria-busy', 'true');
        ledger.replaceChildren(skeleton.content.cloneNode(true));
        syncForm(url);
        showError('');
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 422) {
                const first = data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
                showError(first || data.message);
                ledger.innerHTML = '';
                return;
            }
            if (!response.ok) throw new Error(String(response.status));
            ledger.innerHTML = data.html || '';
            if (exportLink && data.export_url) exportLink.href = data.export_url;
            if (push) window.history.pushState({ fundLedger: true }, '', url);
        } catch (error) {
            if (error.name === 'AbortError') return;
            ledger.innerHTML = '';
            const message = document.createElement('p');
            message.className = 'px-5 py-8 text-center text-sm font-semibold text-red-600';
            message.textContent = i18n.loadError || '';
            ledger.append(message);
        } finally {
            ledger.setAttribute('aria-busy', 'false');
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load(formUrl());
    });
    // A type pill keeps the other filters; choosing a member applies at once.
    pills.forEach((pill) => pill.addEventListener('click', (event) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey) return;
        event.preventDefault();
        typeInput.value = pill.dataset.fundFilter;
        load(formUrl());
    }));
    form.querySelector('input[name="member"]')?.addEventListener('change', () => load(formUrl()));
    reset.addEventListener('click', (event) => {
        event.preventDefault();
        load(reset.href);
    });
    // Pagination links come with each loaded table.
    ledger.addEventListener('click', (event) => {
        const link = event.target.closest('[data-fund-pagination] a[href]');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;
        event.preventDefault();
        load(link.href);
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    window.addEventListener('popstate', () => load(window.location.href, false));
}
