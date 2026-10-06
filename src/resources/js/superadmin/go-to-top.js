/**
 * Superadmin "back to top" button ([data-sa-go-top] in superadmin/layout.blade.php): appears after
 * scrolling down and smoothly scrolls the page (and the content pane, when it scrolls itself) to the top.
 */
const THRESHOLD = 320;

export function initSuperadminGoToTop() {
    const button = document.querySelector('[data-sa-go-top]');
    if (!button || button.dataset.bound) return;
    button.dataset.bound = '1';
    const pane = document.querySelector('.superadmin-content');
    const offset = () => Math.max(window.scrollY || document.documentElement.scrollTop || 0, pane?.scrollTop || 0);
    const sync = () => { button.hidden = offset() < THRESHOLD; };
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    window.addEventListener('scroll', sync, { passive: true });
    pane?.addEventListener('scroll', sync, { passive: true });
    button.addEventListener('click', () => {
        const behavior = reduceMotion ? 'auto' : 'smooth';
        window.scrollTo({ top: 0, behavior });
        pane?.scrollTo?.({ top: 0, behavior });
    });
    sync();
}
