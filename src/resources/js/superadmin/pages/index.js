/**
 * Superadmin page modules. A page opts in by rendering <x-superadmin.page-script name="…">, which outputs a hidden
 * [data-sa-page] element carrying the page's translations (data-i18n) and URLs (data-urls).
 */
import initAdminDetailPage from './admin-detail';
import initAdminsPage from './admins';
import initAuditPage from './audit';
import initCampaignsPage from './campaigns';
import initDashboardPage from './dashboard';
import initFeedbacksPage from './feedbacks';
import initNotificationsPage from './notifications';
import initQueuePage from './queue';
import initRoomDetailPage from './room-detail';
import initRoomsPage from './rooms';
import initSocketPage from './socket';
import initSystemPage from './system';
import initUserDetailPage from './user-detail';
import initUsersPage from './users';

const PAGES = {
    'admin-detail': initAdminDetailPage,
    admins: initAdminsPage,
    audit: initAuditPage,
    campaigns: initCampaignsPage,
    dashboard: initDashboardPage,
    feedbacks: initFeedbacksPage,
    notifications: initNotificationsPage,
    queue: initQueuePage,
    'room-detail': initRoomDetailPage,
    rooms: initRoomsPage,
    socket: initSocketPage,
    system: initSystemPage,
    'user-detail': initUserDetailPage,
    users: initUsersPage,
};

const parse = (value) => {
    try {
        return JSON.parse(value || '{}');
    } catch {
        return {};
    }
};

export function initSuperadminPages() {
    document.querySelectorAll('[data-sa-page]').forEach((root) => {
        const init = PAGES[root.dataset.saPage];
        if (init) init(root, parse(root.dataset.i18n), parse(root.dataset.urls));
    });
}
