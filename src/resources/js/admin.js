import './bootstrap';
import { initToastNotifications } from './shared/toast';
import { initLazyImages } from './shared/lazy-image';

initLazyImages();

import { initAdminAlertModal } from './admin/alert-modal';
import { initAdminGoToTop } from './admin/go-to-top';
import { initUiEnhancements, renderTableSkeleton, debounce, toggleAdminSidebar } from './admin/ui-enhancements';
import { initAdminAuth } from './admin/auth';
import { initAdminAudit } from './admin/audit';
import { initAdminCampaigns } from './admin/campaigns';
import { campaignCreateComponent } from './admin/campaign-create';
import { initCloseCampaignModal } from './admin/close-campaign-modal';
import { initAdminDashboard } from './admin/dashboard';
import { initAdminDebts } from './admin/debts';
import { initAdminNotifications } from './admin/notifications';
import { initAdminOrders } from './admin/orders';
import { initAdminOrderOnBehalf } from './admin/order-on-behalf';
import { initAdminConfirmForms } from './admin/confirm-form';
import { initCampaignOrderingLock } from './admin/campaign-ordering-lock';
import { initAdminReports } from './admin/reports';
import { initAdminSettings } from './admin/settings';
import { initAdminUsers } from './admin/users';
import { initAdminLoading, initAdminReloadButtons, initFilterFormSkeletons } from './admin/loading';
import { initAdminRealtime } from './admin/realtime';
import { initAdminBilling } from './admin/billing';
import { initAdminPaymentReminder } from './admin/payment-reminder';
import { initSponsorDrawStage } from './sponsor-draw/stage';
import { initSponsorDrawCancelModal } from './sponsor-draw/cancel-modal';

export {
    initAdminGoToTop,
    initUiEnhancements,
    renderTableSkeleton,
    debounce,
    toggleAdminSidebar,
    initAdminAuth,
    initAdminAudit,
    initAdminCampaigns,
    campaignCreateComponent,
    initCloseCampaignModal,
    initAdminDashboard,
    initAdminDebts,
    initAdminNotifications,
    initAdminOrders,
    initAdminOrderOnBehalf,
    initCampaignOrderingLock,
    initAdminReports,
    initAdminSettings,
    initAdminUsers,
    initAdminLoading,
    initAdminReloadButtons,
};

// Expose globals for Alpine and inline calls
if (typeof window !== 'undefined') {
    window.campaignCreateComponent = campaignCreateComponent;
    window.renderTableSkeleton = renderTableSkeleton;
    window.debounce = debounce;
    window.toggleAdminSidebar = toggleAdminSidebar;
    window.__DF_ROOM_SLUG__ = window.__DF_ROOM_SLUG__ || document.querySelector('meta[name="room-slug"]')?.content || document.body?.dataset?.roomSlug || '';
}

document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    // Runs after initToastNotifications() so this modal-based override wins over the toast-based
    // `window.alert` it sets: every alert(...) in the admin bundle should open a modal, not a toast.
    initAdminAlertModal();
    initAdminConfirmForms();
    window.__DF_ROOM_SLUG__ = window.__DF_ROOM_SLUG__ || document.querySelector('meta[name="room-slug"]')?.content || document.body?.dataset?.roomSlug || '';

    initUiEnhancements();
    initAdminGoToTop();
    initAdminAuth();
    initAdminAudit();
    initAdminCampaigns();
    initCloseCampaignModal();
    initAdminDashboard();
    initAdminDebts();
    initAdminNotifications();
    initAdminOrders();
    initAdminOrderOnBehalf();
    initCampaignOrderingLock();
    initAdminReports();
    initAdminBilling();
    initAdminPaymentReminder();
    initSponsorDrawStage();
    initSponsorDrawCancelModal();
    initAdminSettings();
    initAdminUsers();
    initAdminLoading();
    initAdminReloadButtons();
    initFilterFormSkeletons();
    // Every admin page (not only the dashboard) reloads into the maintenance screen when it starts, and room
    // admins also receive their private live notifications (e.g. campaign ordering closes soon).
    initAdminRealtime();
});
