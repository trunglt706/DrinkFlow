/**
 * "Cancel the session" modal of the admin sponsor draw page (admin/sponsor-draws/show.blade.php):
 * the header button opens it to ask for the reason; it reopens on load when the reason failed validation.
 */
export function initSponsorDrawCancelModal() {
    const modal = document.querySelector('[data-sd-cancel-modal]');
    if (!modal || modal.dataset.bound) return;
    modal.dataset.bound = '1';

    const reason = modal.querySelector('[data-sd-cancel-reason]');
    let opener = null;

    const open = (trigger) => {
        opener = trigger || null;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        reason?.focus();
    };
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        opener?.focus();
    };

    document.querySelectorAll('[data-sd-cancel-open]').forEach((button) => button.addEventListener('click', () => open(button)));
    modal.querySelectorAll('[data-sd-cancel-dismiss]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

    if (modal.hasAttribute('data-open-on-load')) open(document.querySelector('[data-sd-cancel-open]'));
}
