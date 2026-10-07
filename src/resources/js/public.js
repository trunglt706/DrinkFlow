import { initToastNotifications } from './shared/toast';

import { initPublicHeader } from './public/header';
import { initFooterNav } from './public/footer';
import { initAuthModal } from './public/auth-modal';
import { initVideoModal } from './public/video-modal';
import { initPublicLoading } from './public/loading';
import { initGoToTop } from './public/go-to-top';
import { initContactPage } from './public/contact';
import { initTermsPage } from './public/terms';
import { connectGuestRealtime, loadSocketClientWhenIdle } from './shared/realtime-reload';
import { initGuideLightbox } from './shared/image-lightbox';

document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    initPublicHeader();
    initFooterNav();
    initAuthModal();
    initVideoModal();
    initPublicLoading();
    initGoToTop();
    initContactPage();
    initTermsPage();
    initGuideLightbox();
    // Reload into the maintenance screen when maintenance starts; the Socket.IO client loads after first paint.
    const { realtimeUrl } = document.body.dataset;
    loadSocketClientWhenIdle(realtimeUrl).then((ready) => ready && connectGuestRealtime(realtimeUrl, 'public'));
});
