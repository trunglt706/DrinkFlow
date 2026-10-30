/**
 * Public play page of a game room (resources/views/public/game-room.blade.php, /play/{token}).
 *
 * The visitor records a win of the player they picked: one screen, pick the category and the loser, confirm. The
 * winner is never sent (the server takes it from the session). Every opening of the sheet is a new result with its own
 * idempotency key, so several results of the same round are recorded one submission at a time. Texts come from the
 * data-i18n JSON of the sheet.
 */
import { initToastNotifications } from './shared/toast';
import {
    firstError, initConfirmForms, notify, postResult, readI18n, reloadWithToast, showPendingToast, startButtonLoading,
    stopButtonLoading, uuid,
} from './shared/game-common';

function initRecordSheet() {
    const dialog = document.querySelector('[data-play-record-dialog]');
    const opener = document.querySelector('[data-play-record-open]');
    if (!dialog || !opener) return;
    const i18n = readI18n(dialog);
    const error = dialog.querySelector('[data-play-record-error]');
    const summary = dialog.querySelector('[data-play-record-summary]');
    const submit = dialog.querySelector('[data-play-record-submit]');
    // Same key while the selection is unchanged: a retry after a network error never records twice.
    let idempotencyKey = uuid();

    const category = () => dialog.querySelector('input[name="rule_item_id"]:checked');
    // One loser per result from this page.
    const loser = () => dialog.querySelector('input[name="loser_id"]:checked');
    const showError = (message) => {
        error.textContent = message || '';
        error.classList.toggle('hidden', !message);
    };
    const render = () => {
        const item = category();
        const picked = loser();
        // Radio icon of the loser chips follows the selection.
        dialog.querySelectorAll('input[name="loser_id"]').forEach((input) => {
            const icon = input.nextElementSibling?.querySelector('.material-symbols-outlined');
            if (!icon) return;
            icon.textContent = input.checked ? 'radio_button_checked' : 'radio_button_unchecked';
            icon.classList.toggle('text-rose-600', input.checked);
            icon.classList.toggle('text-slate-300', !input.checked);
        });
        const ready = Boolean(item) && Boolean(picked);
        submit.disabled = !ready;
        summary.textContent = ready
            ? String(i18n.summary || '')
                .replace(':winner', dialog.dataset.winner || '')
                .replace(':losers', picked.dataset.name || '')
                .replace(':category', item.dataset.name || '')
                .replace(':points', item.dataset.points || '0')
            : (i18n.summaryEmpty || '');
    };

    // Fewer taps: pre-select the first enabled category and loser, so a common result is a single "Confirm".
    const selectFirst = (selector) => {
        const inputs = [...dialog.querySelectorAll(selector)];
        if (inputs.some((input) => input.checked)) return;
        const first = inputs.find((input) => !input.disabled);
        if (first) first.checked = true;
    };

    opener.addEventListener('click', () => {
        selectFirst('input[name="rule_item_id"]');
        selectFirst('input[name="loser_id"]');
        idempotencyKey = uuid();
        showError('');
        render();
        dialog.showModal();
    });
    dialog.querySelector('[data-play-record-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('change', () => {
        idempotencyKey = uuid();
        showError('');
        render();
    });
    submit.addEventListener('click', async () => {
        if (submit.disabled) return;
        startButtonLoading(submit);
        showError('');
        try {
            const { ok, data } = await postResult(dialog.dataset.url, {
                rule_item_id: Number(category()?.value),
                loser_id: Number(loser()?.value),
                idempotency_key: idempotencyKey,
            });
            if (!ok) {
                stopButtonLoading(submit);
                render();
                showError(firstError(data) || i18n.error);
                return;
            }
            // Keep the spinner until the page has reloaded.
            reloadWithToast(data.message);
        } catch {
            stopButtonLoading(submit);
            render();
            showError(i18n.networkError);
        }
    });
    render();
}

/** Share dialog: QR code of the public link, copy, and the OS share sheet when the browser has one. */
function initShareDialog() {
    const dialog = document.querySelector('[data-play-share-dialog]');
    const opener = document.querySelector('[data-play-share-open]');
    if (!dialog || !opener) return;
    const i18n = readI18n(dialog);
    const url = dialog.dataset.url;
    const canvas = dialog.querySelector('[data-play-share-qr]');
    const native = dialog.querySelector('[data-play-share-native]');
    native.disabled = typeof navigator.share !== 'function';

    opener.addEventListener('click', () => {
        if (window.QRCode?.toCanvas && !canvas.dataset.drawn) {
            window.QRCode.toCanvas(canvas, url, { width: 216, margin: 1 });
            canvas.dataset.drawn = '1';
        }
        dialog.showModal();
    });
    dialog.querySelector('[data-play-share-close]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-play-share-copy]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // Clipboard API unavailable (plain http): select the link so it can be copied by hand.
            dialog.querySelector('[data-play-share-link]').select();
            document.execCommand?.('copy');
        }
        notify(i18n.copied || url);
    });
    native.addEventListener('click', () => {
        navigator.share?.({ title: dialog.dataset.title || document.title, url }).catch(() => {});
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    initConfirmForms();
    initRecordSheet();
    initShareDialog();
    showPendingToast();
});
