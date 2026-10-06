/**
 * Superadmin audit log (superadmin/audit.blade.php): the per-row "detail" button opens #audit-detail-modal
 * with the entry's context (actor, target, IP, device, user agent) and its before / after / metadata JSON.
 * Values are written with textContent, never as HTML.
 */
const pretty = (value) => (typeof value === 'string' ? value : JSON.stringify(value, null, 2));
const isEmpty = (value) => value === null || value === undefined || value === ''
    || (typeof value === 'object' && Object.keys(value).length === 0);

export default function initAuditPage() {
    const modal = document.querySelector('#audit-detail-modal');
    if (!modal) return;

    const show = (detail) => {
        modal.querySelectorAll('[data-audit-field]').forEach((element) => {
            const value = detail[element.dataset.auditField];
            element.textContent = isEmpty(value) ? '—' : String(value);
        });
        modal.querySelector('[data-audit-field="event_label"]').title = detail.event || '';
        ['before', 'after', 'metadata'].forEach((block) => {
            const pre = modal.querySelector(`[data-audit-block="${block}"]`);
            const empty = modal.querySelector(`[data-audit-block-empty="${block}"]`);
            const value = detail[block];
            pre.hidden = isEmpty(value);
            empty.hidden = !pre.hidden;
            pre.textContent = pre.hidden ? '' : pretty(value);
        });
        window.openSuperadminModal('audit-detail-modal');
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-audit-detail]');
        if (!button) return;
        try {
            show(JSON.parse(button.dataset.auditDetail || '{}'));
        } catch {
            // A malformed payload simply opens nothing.
        }
    });
}
