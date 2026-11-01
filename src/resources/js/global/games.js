/**
 * Game pages of the /me portal (resources/views/user/global/games/*): copy / QR of the public link, confirmation of
 * the forms that change data, player rows of the create-room form, item rows of the rule set form, and the
 * record-result sheet of the room dashboard. Texts come from the data-i18n JSON of the Blade elements; the backend
 * validates everything again (App\Services\Game\*).
 */

import {
    firstError, initConfirmForms, notify, postResult, readI18n, reloadIfStale, reloadWithToast, showPendingToast, startButtonLoading,
    stopButtonLoading, uuid,
} from '../shared/game-common';

function initCopyAndQr(page, i18n) {
    page.addEventListener('click', async (event) => {
        const copy = event.target instanceof Element ? event.target.closest('[data-copy-link]') : null;
        if (copy) {
            const url = copy.dataset.copyLink;
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                // Clipboard API unavailable (http): select the text so the user can copy it by hand.
                const input = page.querySelector('[data-public-link]');
                input?.select();
                document.execCommand?.('copy');
            }
            notify(i18n.copied || url);
            return;
        }

        const qr = event.target instanceof Element ? event.target.closest('[data-qr-toggle]') : null;
        if (qr) {
            const panel = page.querySelector('[data-qr-panel]');
            const canvas = page.querySelector('[data-qr-canvas]');
            if (!panel || !canvas) return;
            const open = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !open);
            panel.classList.toggle('flex', open);
            qr.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && window.QRCode?.toCanvas && !canvas.dataset.drawn) {
                window.QRCode.toCanvas(canvas, qr.dataset.qrToggle, { width: 200, margin: 1 });
                canvas.dataset.drawn = '1';
            }
        }
    });
}

function initRoomCreate() {
    const form = document.querySelector('[data-room-create]');
    if (!form) return;
    const list = form.querySelector('[data-player-rows]');
    const count = form.querySelector('[data-player-count]');
    const min = Number(form.dataset.min || 2);
    const max = Number(form.dataset.max || 20);
    const i18n = readI18n(form);

    const rows = () => [...list.querySelectorAll('[data-player-row]')];
    const renumber = () => {
        rows().forEach((row, index) => {
            row.querySelector('[data-player-number]').textContent = String(index + 1);
            row.querySelector('input').placeholder = String(i18n.placeholder || ':number').replace(':number', String(index + 1));
        });
        const total = rows().length;
        count.value = String(total);
        form.querySelector('[data-add-player]').disabled = total >= max;
        list.querySelectorAll('[data-remove-player]').forEach((button) => { button.disabled = total <= min; });
    };
    const addRow = () => {
        const clone = rows()[0].cloneNode(true);
        clone.querySelector('input').value = '';
        list.appendChild(clone);
    };
    const resize = (wanted) => {
        while (rows().length < wanted) addRow();
        while (rows().length > wanted) rows().at(-1).remove();
        renumber();
    };

    count.addEventListener('change', () => resize(Math.min(max, Math.max(min, Number(count.value)))));
    form.querySelector('[data-add-player]').addEventListener('click', () => {
        if (rows().length < max) addRow();
        renumber();
        rows().at(-1).querySelector('input').focus();
    });
    list.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-remove-player]') : null;
        if (!button || rows().length <= min) return;
        button.closest('[data-player-row]').remove();
        renumber();
    });
    renumber();
}

function initRuleSetForm() {
    const form = document.querySelector('[data-rule-set-form]');
    if (!form) return;
    const list = form.querySelector('[data-rule-items]');
    const rows = () => [...list.querySelectorAll('[data-rule-item]')];

    // Inputs are named items[i][field]: renumber after every move so the order posted is the order shown.
    const reindex = () => {
        rows().forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `items[${index}][${input.dataset.field}]`;
            });
        });
        list.querySelectorAll('[data-remove-item]').forEach((button) => { button.disabled = rows().length <= 1; });
    };

    form.querySelector('[data-add-item]').addEventListener('click', () => {
        const clone = rows()[0].cloneNode(true);
        clone.querySelector('[data-field="name"]').value = '';
        clone.querySelector('[data-field="points"]').value = '1';
        clone.querySelector('[data-field="is_active"]').checked = true;
        list.appendChild(clone);
        reindex();
        clone.querySelector('[data-field="name"]').focus();
    });
    list.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const row = target?.closest('[data-rule-item]');
        if (!row) return;
        if (target.closest('[data-remove-item]') && rows().length > 1) row.remove();
        if (target.closest('[data-move="up"]') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (target.closest('[data-move="down"]') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        reindex();
    });
    reindex();
}

function initRecordDialog() {
    const dialog = document.querySelector('[data-record-dialog]');
    const opener = document.querySelector('[data-record-open]');
    if (!dialog || !opener) return;
    const form = dialog.querySelector('[data-record-form]');
    const i18n = readI18n(dialog);
    const error = dialog.querySelector('[data-record-error]');
    const back = dialog.querySelector('[data-record-back]');
    const next = dialog.querySelector('[data-record-next]');
    const submit = dialog.querySelector('[data-record-submit]');
    let step = 1;
    // One key per result being entered: a retry after a network error re-sends the same key, so the server
    // answers the first result instead of recording it twice. A changed selection is a new result: new key.
    let idempotencyKey = uuid();

    const category = () => form.querySelector('input[name="rule_item_id"]:checked');
    const winner = () => form.querySelector('input[name="winner_id"]:checked');
    const losers = () => [...form.querySelectorAll('input[name="loser_ids[]"]:checked')];

    const showError = (message) => {
        error.textContent = message || '';
        error.classList.toggle('hidden', !message);
    };
    // Fewer taps: pre-select the first enabled option of a group when nothing is picked yet.
    const selectFirst = (selector) => {
        const inputs = [...form.querySelectorAll(selector)];
        if (inputs.some((input) => input.checked)) return;
        const first = inputs.find((input) => !input.disabled);
        if (first) first.checked = true;
    };
    const render = () => {
        dialog.querySelectorAll('[data-record-step]').forEach((panel) => { panel.hidden = Number(panel.dataset.recordStep) !== step; });
        back.disabled = step === 1;
        back.classList.toggle('invisible', step === 1);
        next.hidden = step === 4;
        submit.hidden = step !== 4;
        if (step === 3) {
            // The winner can never be a loser.
            const winnerId = winner()?.value;
            form.querySelectorAll('input[name="loser_ids[]"]').forEach((input) => {
                const isWinner = input.value === winnerId;
                input.disabled = isWinner;
                if (isWinner) input.checked = false;
            });
            selectFirst('input[name="loser_ids[]"]');
        }
        if (step === 4) {
            dialog.querySelector('[data-record-summary]').textContent = String(i18n.summary || '')
                .replace(':winner', winner()?.dataset.name || '')
                .replace(':losers', losers().map((input) => input.dataset.name).join(', '))
                .replace(':category', category()?.dataset.name || '')
                .replace(':points', category()?.dataset.points || '0');
        }
    };
    const validate = () => {
        if (step === 1 && !category()) return i18n.needCategory;
        if (step === 2 && !winner()) return i18n.needWinner;
        if (step === 3 && losers().length === 0) return i18n.needLosers;
        return '';
    };
    const reset = () => {
        form.reset();
        selectFirst('input[name="rule_item_id"]');
        selectFirst('input[name="winner_id"]');
        step = 1;
        idempotencyKey = uuid();
        showError('');
        render();
    };

    opener.addEventListener('click', () => {
        reset();
        dialog.showModal();
    });
    dialog.querySelector('[data-record-close]').addEventListener('click', () => dialog.close());
    form.addEventListener('change', () => {
        idempotencyKey = uuid();
        showError('');
    });
    back.addEventListener('click', () => {
        step = Math.max(1, step - 1);
        showError('');
        render();
    });
    next.addEventListener('click', () => {
        const message = validate();
        showError(message);
        if (message) return;
        step = Math.min(4, step + 1);
        render();
    });
    submit.addEventListener('click', async () => {
        if (submit.disabled) return;
        startButtonLoading(submit);
        showError('');
        try {
            const { ok, data } = await postResult(dialog.dataset.url, {
                rule_item_id: Number(category()?.value),
                winner_id: Number(winner()?.value),
                loser_ids: losers().map((input) => Number(input.value)),
                idempotency_key: idempotencyKey,
                round: dialog.dataset.round ? Number(dialog.dataset.round) : null,
            });
            if (!ok) {
                // Out-of-date page (new round, room finished, player lost): reload and show why.
                if (reloadIfStale(data)) return;
                stopButtonLoading(submit);
                showError(firstError(data) || i18n.error);
                return;
            }
            // Reload the dashboard (same room) and show the toast once it is rendered; the spinner stays meanwhile.
            reloadWithToast(data.message);
        } catch {
            stopButtonLoading(submit);
            showError(i18n.networkError);
        }
    });
    render();
}

function initVoidDialog() {
    const dialog = document.querySelector('[data-void-dialog]');
    if (!dialog) return;
    const form = dialog.querySelector('[data-void-form]');
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-void-open]') : null;
        if (!button) return;
        form.action = button.dataset.voidUrl;
        form.reset();
        dialog.showModal();
        form.querySelector('input[name="reason"]').focus();
    });
    dialog.querySelector('[data-void-cancel]').addEventListener('click', () => dialog.close());
}

export function initGames() {
    const page = document.querySelector('[data-games-page]');
    if (page && !page.dataset.bound) {
        page.dataset.bound = '1';
        initCopyAndQr(page, readI18n(page));
    }
    initConfirmForms();
    initRoomCreate();
    initRuleSetForm();
    initRecordDialog();
    initVoidDialog();
    showPendingToast();
}
