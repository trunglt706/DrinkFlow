/**
 * Realtime connection shared by every admin page.
 *
 * Room admins (body[data-admin-socket-token-url]) open an authenticated socket that joins their private
 * `admin:{id}` channel, so notifications such as "campaign ordering closes soon" show up as a toast and in the
 * header bell on whatever admin page is open. Pages without a room (and superadmins) fall back to the anonymous
 * socket, which only carries maintenance notices.
 */
import { attachSocketDebugLogger } from '../shared/socket-debug';
import { connectGuestRealtime, listenMaintenance } from '../shared/realtime-reload';

const DEADLINE_REMINDER = 'campaign.deadline_reminder';

const parseI18n = (raw) => {
    try {
        return JSON.parse(raw || '{}');
    } catch (error) {
        return {};
    }
};

const fillTemplate = (template, values) => String(template || '')
    .replace(/:(\w+)/g, (match, key) => (values[key] !== undefined ? String(values[key]) : match));

const formatTime = (iso) => {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
};

/**
 * Title and body of an admin notification in the page's language (falls back to the server-rendered text).
 *
 * @param {{type?: string, title?: string, body?: string, data?: object}} payload `admin.notification.created` payload.
 * @param {Record<string, string>} i18n Translations from data-admin-realtime-i18n.
 * @returns {{title: string, body: string}}
 */
function present(payload, i18n) {
    const data = payload?.data || {};
    if (payload?.type === DEADLINE_REMINDER && data.deadline && i18n.deadline_reminder_body) {
        return {
            title: i18n.deadline_reminder_title || payload.title || '',
            body: fillTemplate(i18n.deadline_reminder_body, {
                campaign: data.campaign_name || data.campaign_code || '',
                time: formatTime(data.deadline),
                pending: Number(data.pending_count) || 0,
                total: Number(data.total_count) || 0,
            }),
        };
    }

    return { title: payload?.title || '', body: payload?.body || '' };
}

/**
 * Prepend a live notification to the header bell dropdown and light its unread indicator.
 *
 * @param {{title: string, body: string}} content Text to show.
 * @param {string} icon Material Symbols icon name.
 * @param {Record<string, string>} i18n Translations from data-admin-realtime-i18n.
 */
function addToBell(content, icon, i18n) {
    const root = document.querySelector('[data-admin-notifications]');
    const list = root?.querySelector('[data-notifications-menu] .max-h-64');
    if (!root || !list) return;

    if (!list.querySelector('[data-unread-notification]')) list.innerHTML = '';

    const item = document.createElement('div');
    item.dataset.unreadNotification = '';
    item.className = 'px-4 py-2.5 hover:bg-surface-container-low transition-colors';
    item.innerHTML = `
        <div class="flex items-start gap-2.5">
            <span class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                <span class="material-symbols-outlined text-[14px]" data-icon></span>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-on-surface leading-snug" data-title></p>
                <p class="mt-0.5 text-[11px] text-outline leading-snug" data-body></p>
                <span class="text-[10px] font-mono text-outline" data-time></span>
            </div>
        </div>`;
    item.querySelector('[data-icon]').textContent = icon;
    item.querySelector('[data-title]').textContent = content.title;
    item.querySelector('[data-body]').textContent = content.body;
    item.querySelector('[data-time]').textContent = i18n.just_now || '';
    list.prepend(item);

    const toggle = root.querySelector('[data-notifications-toggle]');
    if (toggle) {
        // Bell badge with the unread count (capped at 99+).
        let badge = toggle.querySelector('[data-unread-indicator]');
        if (!badge) {
            badge = document.createElement('span');
            badge.dataset.unreadIndicator = '';
            badge.dataset.count = '0';
            badge.className = 'absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-error text-white text-[9px] font-bold font-mono leading-4 text-center ring-2 ring-surface pointer-events-none';
            toggle.appendChild(badge);
        }
        const total = (Number(badge.dataset.count) || 0) + 1;
        badge.dataset.count = String(total);
        badge.textContent = total > 99 ? '99+' : String(total);
    }

    const count = root.querySelector('[data-unread-count]');
    if (count) {
        count.textContent = String((Number(count.textContent) || 0) + 1);
    } else {
        const markAll = root.querySelector('[data-mark-all-read]');
        const badge = document.createElement('span');
        badge.dataset.unreadCount = '';
        badge.className = 'px-1.5 py-0.5 rounded-full bg-error-container text-error text-[10px] font-mono font-bold';
        badge.textContent = '1';
        markAll?.before(badge);
    }
}

/**
 * Show a private admin notification as a toast, and in the bell when it belongs to the room being viewed.
 *
 * @param {object} payload `admin.notification.created` payload.
 * @param {Record<string, string>} i18n Translations from data-admin-realtime-i18n.
 */
function handleAdminNotification(payload, i18n) {
    const content = present(payload, i18n);
    const message = content.body ? `${content.title}: ${content.body}` : content.title;
    if (message && typeof window.notify === 'function') window.notify(message, 'warning');

    if (Number(payload?.room_id) === Number(document.body.dataset.roomId)) {
        addToBell(content, payload?.type === DEADLINE_REMINDER ? 'alarm' : 'notifications', i18n);
    }
}

/** Connect the admin page to the realtime server (authenticated for room admins, anonymous otherwise). */
export function initAdminRealtime() {
    const { maintenanceRealtimeUrl: realtimeUrl, adminSocketTokenUrl: tokenUrl } = document.body.dataset;
    if (!window.io || !realtimeUrl) return;
    if (!tokenUrl) {
        connectGuestRealtime(realtimeUrl, 'admin');
        return;
    }

    const i18n = parseI18n(document.body.dataset.adminRealtimeI18n);
    const fetchToken = () => fetch(tokenUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : null))
        .then((json) => json?.data?.token || null)
        .catch(() => null);

    fetchToken().then((token) => {
        if (!token) {
            connectGuestRealtime(realtimeUrl, 'admin');
            return;
        }

        let firstToken = token;
        const socket = window.io(realtimeUrl, {
            // Tokens are short-lived: fetch a fresh one on every reconnect instead of replaying an expired one.
            auth: (callback) => {
                if (firstToken) {
                    callback({ token: firstToken });
                    firstToken = null;
                    return;
                }
                fetchToken().then((fresh) => callback(fresh ? { token: fresh } : {}));
            },
            transports: ['websocket', 'polling'],
        });
        attachSocketDebugLogger(socket, 'admin');
        listenMaintenance(socket);
        socket.on('admin.notification.created', (payload) => handleAdminNotification(payload, i18n));
    });
}
