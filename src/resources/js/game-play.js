/**
 * Public play page of a game room (resources/views/public/game-room.blade.php, /play/{token}).
 *
 * The visitor records a win of the player they picked: one screen, pick the category and the loser, confirm. The
 * winner is never sent (the server takes it from the session). Every opening of the sheet is a new result with its own
 * idempotency key, so several results of the same round are recorded one submission at a time. Texts come from the
 * data-i18n JSON of the sheet. The page reloads by itself when the owner starts or finishes the room (realtime).
 */
import { initToastNotifications } from './shared/toast';
import {
    firstError, initConfirmForms, notify, postResult, readI18n, reloadIfStale, reloadWithToast, showPendingToast, startButtonLoading,
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
                round: dialog.dataset.round ? Number(dialog.dataset.round) : null,
            });
            if (!ok) {
                // Out-of-date page (new round, room finished, player lost): reload and show why.
                if (reloadIfStale(data)) return;
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

/**
 * Live status: the page joins its room channel (game:{id}) and reloads when the owner starts / finishes the room or
 * opens a new round.
 * Tokens are short-lived; a refused reconnect asks for a fresh one.
 */
function initRealtimeReload() {
    const { playRealtimeUrl: realtimeUrl, playSocketTokenUrl: tokenUrl } = document.body.dataset;
    if (!realtimeUrl || !tokenUrl || typeof window.io !== 'function') return;
    let socket = null;
    let retry = null;
    // Do not pull the page from under a visitor filling the record sheet: reload once it is closed (a result sent
    // meanwhile for the old round is refused by the server anyway).
    const reloadWhenIdle = () => {
        const sheet = document.querySelector('[data-play-record-dialog]');
        if (sheet?.open) {
            sheet.addEventListener('close', () => window.location.reload(), { once: true });
            return;
        }
        window.location.reload();
    };

    const connect = async () => {
        clearTimeout(retry);
        try {
            const response = await fetch(tokenUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            // 404 / 410: the link was revoked or the room closed; reloading shows the right page.
            if (response.status === 404 || response.status === 410) {
                window.location.reload();
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { data } = await response.json();
            socket?.disconnect();
            socket = window.io(realtimeUrl, { auth: { token: data.token }, transports: ['websocket', 'polling'], reconnection: false });
            socket.on('game_room.status_changed', reloadWhenIdle);
            socket.on('disconnect', () => { retry = setTimeout(connect, 3000); });
            socket.on('connect_error', () => { socket.disconnect(); retry = setTimeout(connect, 5000); });
        } catch {
            retry = setTimeout(connect, 10000);
        }
    };
    connect();
}

document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    initRealtimeReload();
    initConfirmForms();
    initRecordSheet();
    initShareDialog();
    showPendingToast();
});
