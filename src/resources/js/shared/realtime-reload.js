/**
 * Realtime events that force the page to reload, so the server re-checks access immediately.
 *
 * - `session.force_reload` (private): the user's trusted device was revoked or the account was deleted.
 * - `system.maintenance` (public channel): maintenance was switched on or scheduled; pages reload into the
 *   maintenance screen. Every page type listens — public visitors, /me, rooms and the admin console.
 */
import { attachSocketDebugLogger } from './socket-debug';

// Spread reloads over a few seconds so every open page does not hit the server at the same instant.
const RELOAD_JITTER_MS = 3000;
// Browsers cap setTimeout at ~24.8 days; farther schedules are picked up by the next page load instead.
const MAX_SCHEDULE_MS = 24 * 60 * 60 * 1000;
// Reload slightly after the scheduled start so the server already sees maintenance as active.
const SCHEDULE_MARGIN_MS = 2000;

let maintenanceTimer = null;

const reloadAfter = (delayMs) => window.setTimeout(() => window.location.reload(), delayMs + Math.random() * RELOAD_JITTER_MS);

/**
 * Reload now when maintenance is active, or at its start when it is scheduled; cancel a pending
 * reload when maintenance was switched off or moved.
 *
 * @param {{active?: boolean, scheduled?: boolean, starts_in?: number|null}} payload `system.maintenance` payload.
 */
function handleMaintenance(payload) {
    window.clearTimeout(maintenanceTimer);
    maintenanceTimer = null;

    if (payload?.active) {
        maintenanceTimer = reloadAfter(0);
        return;
    }

    const startsInMs = Number(payload?.starts_in) * 1000;
    if (payload?.scheduled && Number.isFinite(startsInMs) && startsInMs <= MAX_SCHEDULE_MS) {
        maintenanceTimer = reloadAfter(Math.max(0, startsInMs) + SCHEDULE_MARGIN_MS);
    }
}

/**
 * Reload when maintenance starts. Attach to any socket that joined the `public` channel.
 *
 * @param {import('socket.io-client').Socket} socket
 */
export function listenMaintenance(socket) {
    socket.on('system.maintenance', handleMaintenance);
}

/**
 * Reload on a private force-reload command and when maintenance starts. Attach to authenticated user sockets.
 *
 * @param {import('socket.io-client').Socket} socket
 */
export function listenForcedReload(socket) {
    socket.on('session.force_reload', () => window.location.reload());
    listenMaintenance(socket);
}

/**
 * Inject the Socket.IO client served by the realtime server once the page has finished loading and the
 * browser is idle, so it never competes with first paint. Resolves false (without a console error from a
 * rejected promise) when the realtime server is unreachable.
 *
 * @param {string|undefined} realtimeUrl Realtime server URL (from `data-realtime-url`).
 * @returns {Promise<boolean>} Whether `window.io` is available.
 */
export function loadSocketClientWhenIdle(realtimeUrl) {
    if (window.io) return Promise.resolve(true);
    if (!realtimeUrl) return Promise.resolve(false);

    return new Promise((resolve) => {
        const inject = () => {
            const script = document.createElement('script');
            script.src = `${realtimeUrl}/socket.io/socket.io.js`;
            script.async = true;
            script.onload = () => resolve(Boolean(window.io));
            script.onerror = () => {
                script.remove();
                resolve(false);
            };
            document.head.appendChild(script);
        };
        const whenIdle = () => (window.requestIdleCallback ? window.requestIdleCallback(inject, { timeout: 3000 }) : window.setTimeout(inject, 1));

        if (document.readyState === 'complete') whenIdle();
        else window.addEventListener('load', whenIdle, { once: true });
    });
}

/**
 * Open an anonymous socket (no token) that only receives system-wide notices such as maintenance.
 * Used where there is no authenticated socket: public pages, admin pages, or when a user token is unavailable.
 *
 * @param {string|undefined} realtimeUrl Realtime server URL (from `data-realtime-url`).
 * @param {string} scope Label for the local debug log, e.g. "public", "admin".
 * @returns {import('socket.io-client').Socket|null} The socket, or null when realtime is unavailable.
 */
export function connectGuestRealtime(realtimeUrl, scope) {
    if (!window.io || !realtimeUrl) return null;

    const socket = window.io(realtimeUrl, { transports: ['websocket', 'polling'] });
    attachSocketDebugLogger(socket, scope);
    listenMaintenance(socket);
    return socket;
}
