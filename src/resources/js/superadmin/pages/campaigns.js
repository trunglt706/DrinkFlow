/**
 * Global campaigns list (superadmin/campaigns.blade.php): force-close / force-cancel from the row ⋯ menu.
 */
import { dfApi, openSuperadminConfirm, pageNotice, reloadSoon, trans } from './common';

export default function initCampaignsPage(_root, i18n) {
    const actions = {
        'force-close': {
            endpoint: 'force-close',
            message: i18n.confirm_force_close,
            description: i18n.force_close_description,
            confirmLabel: i18n.force_close,
            confirmIcon: 'stop_circle',
            tone: 'warning',
            irreversible: true,
            done: i18n.force_closed,
        },
        'force-cancel': {
            endpoint: 'force-cancel',
            message: i18n.confirm_force_cancel,
            description: i18n.force_cancel_description,
            confirmLabel: i18n.force_cancel,
            confirmIcon: 'cancel',
            tone: 'danger',
            irreversible: false,
            done: i18n.force_cancelled,
        },
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="force-close"], [data-action="force-cancel"]');
        if (!button) return;
        const action = actions[button.dataset.action];
        openSuperadminConfirm({
            message: trans(action.message, { name: button.dataset.campaignName }),
            description: action.description,
            confirmLabel: action.confirmLabel,
            confirmIcon: action.confirmIcon,
            tone: action.tone,
            irreversible: action.irreversible,
            onConfirm: async () => {
                await dfApi(`/superadmin/campaigns/${button.dataset.campaignId}/${action.endpoint}`, { method: 'POST' });
                pageNotice(action.done);
                reloadSoon();
            },
        });
    });
}
