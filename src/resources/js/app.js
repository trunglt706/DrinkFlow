import './bootstrap';
import { initToastNotifications } from './shared/toast';
import { initLazyImages } from './shared/lazy-image';

initToastNotifications();
initLazyImages();

import { initPublicHeader } from './public/header';
import { initAuthModal } from './public/auth-modal';
import { initVideoModal } from './public/video-modal';
import { initPublicLoading } from './public/loading';
import { initGoToTop } from './public/go-to-top';
import { initContactPage } from './public/contact';
import { initTermsPage } from './public/terms';

import { initGlobalHeader } from './global/header';
import { initLogoutModal } from './global/logout-modal';
import { initGlobalLoading } from './global/loading';
import { initGlobalGoToTop } from './global/go-to-top';

import { initSuperadminLoading, initSuperadminReloadButtons } from './superadmin/loading';
import { initSuperadminModals } from './superadmin/modal';
import { initSuperadminSearchClear } from './superadmin/search-clear';
import { initSuperadminNotifications } from './superadmin/notifications';
import { initSuperadminRealtime } from './superadmin/realtime';
import { initSuperadminSidebar } from './superadmin/sidebar';
import { initSuperadminShell } from './superadmin/shell';
import { initSuperadminVersions } from './superadmin/versions';
import { initSuperadminDashboard } from './superadmin/dashboard';
import { initSuperadminPages } from './superadmin/pages';
import { initSuperadminPermissionMatrix } from './superadmin/permission-matrix';
import { initSuperadminGoToTop } from './superadmin/go-to-top';
import { initSuperadminSensitive } from './superadmin/sensitive';
import { initDateRangePickers } from './admin/ui-enhancements';

document.addEventListener('DOMContentLoaded', () => {
    // Public Components & Pages
    initPublicHeader();
    initAuthModal();
    initVideoModal();
    initPublicLoading();
    initGoToTop();
    initContactPage();
    initTermsPage();

    // Global User Components & Pages
    initGlobalHeader();
    initLogoutModal();
    initGlobalLoading();
    initGlobalGoToTop();

    // Superadmin Components & Pages
    initSuperadminLoading();
    initSuperadminReloadButtons();
    initSuperadminModals();
    initSuperadminSearchClear();
    initSuperadminNotifications();
    initSuperadminRealtime();
    initSuperadminShell();
    initSuperadminSidebar();
    initSuperadminVersions();
    initSuperadminDashboard();
    initSuperadminPages();
    initSuperadminPermissionMatrix();
    initSuperadminGoToTop();
    initSuperadminSensitive();
    initDateRangePickers();
});
