import './bootstrap';
import { initToastNotifications } from './shared/toast';

import { initGlobalHeader } from './global/header';
import { initLogoutModal } from './global/logout-modal';
import { initGlobalLoading } from './global/loading';
import { initGlobalGoToTop } from './global/go-to-top';
import { initGlobalDashboard } from './global/dashboard';
import { initGlobalFooterNav } from './global/footer';
import { initGlobalFeedback } from './global/feedback';
import { initGlobalNotifications } from './global/notifications';
import { initSessionRevocation } from './global/session-revocation';
import { initDesktopNotifications } from './global/desktop-notification';
import { initGuideLightbox } from './shared/image-lightbox';
import { initProfileBioEditor } from './global/profile-bio';
import { initGames } from './global/games';

document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    initGlobalHeader();
    initLogoutModal();
    initGlobalLoading();
    initGlobalGoToTop();
    initGlobalDashboard();
    initGlobalFooterNav();
    initGlobalFeedback();
    initGlobalNotifications();
    initDesktopNotifications();
    initSessionRevocation();
    initGuideLightbox();
    initProfileBioEditor();
    initGames();
});
