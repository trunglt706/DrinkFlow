/**
 * Socket.IO monitoring (superadmin/socket.blade.php): gateway status, connection counts and recent disconnects,
 * refreshed every 30 seconds.
 */
import { dfApi, escapeHtml, renderEmpty, statusPill } from './common';

export default function initSocketPage(_root, i18n, urls) {
    const disconnects = document.querySelector('#disconnects');

    const load = async () => {
        const { data } = await dfApi(urls.index);
        document.querySelector('#gateway-status').innerHTML = statusPill(data.status);
        // When the check fails, the server explains why (wrong secret, gateway down, ...) — see SocketHealthReason.
        document.querySelector('#gateway-reason').textContent = data.reason_message || i18n.gateway_health;
        document.querySelector('#connected-users').textContent = data.connected_users ?? '—';
        document.querySelector('#connected-admins').textContent = (data.connected_admins ?? 0) + (data.connected_superadmins ?? 0);
        document.querySelector('#auth-failures').textContent = data.authentication_failures ?? '—';
        if ((data.recent_disconnects || []).length) {
            disconnects.innerHTML = data.recent_disconnects.map((item) =>
                `<div class="sa-health-row"><div><strong>${escapeHtml(item.actor_type)}</strong><small>${escapeHtml(item.reason)} · ${escapeHtml(item.at)}</small></div></div>`,
            ).join('');
        } else {
            renderEmpty(disconnects, 'tpl-no-disconnects');
        }
    };

    load().catch((error) => renderEmpty(disconnects, 'tpl-load-failed', error.message));
    window.setInterval(() => load().catch(() => {}), 30000);
}
