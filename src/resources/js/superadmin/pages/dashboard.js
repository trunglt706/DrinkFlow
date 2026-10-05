/**
 * Dashboard overview (superadmin/dashboard.blade.php): headline KPIs and the system health list.
 * The analytics section below them is driven by resources/js/superadmin/dashboard.js.
 */
import { dfApi, pageNotice, statusPill, trans } from './common';

const formatBytes = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    let value = Number(bytes) || 0;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${new Intl.NumberFormat(document.documentElement.lang || undefined, { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${units[unit]}`;
};

export default function initDashboardPage(_root, i18n, urls) {
    const storageUsageText = (storage) => {
        const driver = storage.driver || storage.disk || '';
        if (storage.used_bytes == null || storage.total_bytes == null) {
            return trans(i18n.storage_usage_unknown, { driver });
        }
        return trans(storage.limit === 'quota' ? i18n.storage_usage_quota : i18n.storage_usage, {
            driver,
            used: formatBytes(storage.used_bytes),
            total: formatBytes(storage.total_bytes),
        });
    };
    const setText = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) element.textContent = value;
    };
    const setHtml = (selector, html) => {
        const element = document.querySelector(selector);
        if (element) element.innerHTML = html;
    };

    dfApi(urls.summary).then(({ data }) => {
        // Figures outside the viewer's permissions come back as null and stay "—".
        setText('#total-rooms', data.total_rooms ?? '—');
        setText('#active-rooms', data.active_rooms === null ? '' : trans(i18n.active_rooms, { count: data.active_rooms }));
        setText('#total-users', data.total_global_users ?? '—');
        setText('#active-users', data.active_global_users === null ? '' : trans(i18n.active_users, { count: data.active_global_users }));
        setText('#total-admins', data.total_admins ?? '—');
        setText('#orders-today', data.orders_today ?? '—');
        const health = data.system_health;
        if (!health) return;
        setHtml('#database-status', statusPill(health.database.status));
        setText('#queue-status', `${health.queue.connection} · ${trans(i18n.failed_count, { count: health.queue.failed_jobs })}`);
        setHtml('#socket-status', statusPill(health.socket.status));
        const socketStatus = document.querySelector('#socket-status');
        if (socketStatus) socketStatus.title = health.socket.reason_message || '';
        setHtml('#mail-status', statusPill(health.mail.configured ? 'configured' : 'not_configured'));
        setHtml('#storage-status', statusPill(health.storage.status));
        setText('#storage-usage', storageUsageText(health.storage));
        if (health.supervisor) {
            document.querySelector('#supervisor-row').hidden = false;
            setHtml('#supervisor-status', statusPill(health.supervisor.status));
        }
    }).catch((error) => pageNotice(error.message, 'error'));
}
