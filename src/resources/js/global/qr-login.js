/**
 * QR sign-in on a phone (/me/devices): while the panel is open, ask the server for a new one-time sign-in link every
 * few seconds and draw it as a QR code. Codes stop after a few minutes (or while the tab is hidden) so an unattended
 * screen does not keep showing valid codes.
 */
const MAX_CODES_PER_RUN = 18;

export function initQrLogin() {
    const root = document.querySelector('[data-qr-login]');
    if (!root) return;

    const toggle = root.querySelector('[data-qr-login-toggle]');
    const toggleLabel = root.querySelector('[data-qr-login-toggle-label]');
    const panel = root.querySelector('[data-qr-login-panel]');
    const canvas = root.querySelector('[data-qr-login-canvas]');
    const status = root.querySelector('[data-qr-login-status]');
    const restart = root.querySelector('[data-qr-login-restart]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let i18n = {};
    try { i18n = JSON.parse(root.dataset.i18n || '{}'); } catch { i18n = {}; }

    let open = false;
    let running = false;
    let issued = 0;
    let rotateTimer = null;
    let tickTimer = null;
    let request = null;

    const clearCanvas = () => canvas.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
    const clearTimers = () => {
        clearTimeout(rotateTimer);
        clearInterval(tickTimer);
        request?.abort();
        request = null;
    };
    const halt = (message) => {
        running = false;
        clearTimers();
        clearCanvas();
        status.textContent = message;
        restart.classList.remove('hidden');
    };
    const countdown = (seconds) => {
        let left = seconds;
        const render = () => { status.textContent = (i18n.countdown || '').replace('__SECONDS__', String(left)); };
        render();
        clearInterval(tickTimer);
        tickTimer = setInterval(() => { left = Math.max(0, left - 1); render(); }, 1000);
    };

    const refresh = async () => {
        if (!open || !running || document.hidden) return;
        if (issued >= MAX_CODES_PER_RUN) {
            halt(i18n.stopped || '');
            return;
        }
        clearTimers();
        request = new AbortController();
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: request.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!open || !running) return;
            if (!window.QRCode?.toCanvas) throw new Error('QRCode library missing');
            await window.QRCode.toCanvas(canvas, data.url, { width: 216, margin: 1 });
            issued += 1;
            const seconds = Number(data.rotate_in) || 10;
            countdown(seconds);
            rotateTimer = setTimeout(refresh, seconds * 1000);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            halt(i18n.error || '');
        }
    };

    const start = () => {
        running = true;
        issued = 0;
        restart.classList.add('hidden');
        status.textContent = i18n.loading || '';
        refresh();
    };
    const setOpen = (value) => {
        open = value;
        panel.classList.toggle('hidden', !open);
        panel.classList.toggle('flex', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggleLabel.textContent = open ? (i18n.hide || '') : (i18n.show || '');
        if (open) {
            start();
        } else {
            running = false;
            clearTimers();
            clearCanvas();
        }
    };

    toggle.addEventListener('click', () => setOpen(!open));
    restart.addEventListener('click', start);
    // A hidden tab stops asking for codes; coming back shows a fresh one right away.
    document.addEventListener('visibilitychange', () => {
        if (!open || !running) return;
        if (document.hidden) {
            clearTimers();
            clearCanvas();
        } else {
            status.textContent = i18n.loading || '';
            refresh();
        }
    });
}
