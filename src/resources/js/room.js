import './bootstrap';
import { initToastNotifications } from './shared/toast';
import { initLazyImages } from './shared/lazy-image';

initLazyImages();

import { initCampaignOrder } from './room/campaign-order';
import { initGlobalGoToTop } from './global/go-to-top';
import { initGlobalLoading } from './global/loading';
import { initRoomRealtime } from './room/realtime';
import { initDesktopNotifications } from './global/desktop-notification';
import { initCampaignDecline } from './room/campaign-decline';
import { initRoomHeader } from './room/header';
import { initRoomDashboardCharts } from './room/dashboard-charts';
import { initGroupedImageLightbox, initGuideLightbox } from './shared/image-lightbox';
import { initSponsorDrawStage } from './sponsor-draw/stage';
import { initItemRatings } from './room/item-rating';

document.addEventListener('DOMContentLoaded', () => {
    initSponsorDrawStage();
    initToastNotifications();
    initCampaignOrder();
    initGlobalGoToTop();
    initGlobalLoading();
    initDesktopNotifications();
    initRoomRealtime();
    initCampaignDecline();
    initRoomHeader();
    initRoomDashboardCharts();
    initGuideLightbox();
    initGroupedImageLightbox();
    initItemRatings();
});
