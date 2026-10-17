/**
 * Full-screen image viewer shared by the guide articles and the campaign menu/cart.
 *
 * The overlay markup comes from <x-image-lightbox />. Close with the X button, Esc or a click on the
 * backdrop; move between the images of the opened set with the prev/next buttons, arrow keys or a swipe.
 */

/**
 * @typedef {{src: string, alt: string, title: string, price: string, description: string}} LightboxEntry
 */

/** @type {{open: (entries: Array<LightboxEntry>, index: number) => void}|null} */
let viewer = null;

/**
 * Wire the page's lightbox overlay once and return its controller.
 *
 * @returns {{open: (entries: Array<LightboxEntry>, index: number) => void}|null} Controller, or null when the page has no overlay.
 */
function getViewer() {
    if (viewer) return viewer;
    const overlay = document.querySelector('[data-image-lightbox]');
    if (!overlay) return null;

    // Escape any stacking context of the page content so the overlay covers the sticky header and modals too.
    document.body.appendChild(overlay);

    const view = overlay.querySelector('[data-image-lightbox-image]');
    const caption = overlay.querySelector('[data-image-lightbox-caption]');
    const details = overlay.querySelector('[data-image-lightbox-details]');
    const titleEl = overlay.querySelector('[data-image-lightbox-title]');
    const priceEl = overlay.querySelector('[data-image-lightbox-price]');
    const descEl = overlay.querySelector('[data-image-lightbox-description]');
    const counter = overlay.querySelector('[data-image-lightbox-counter]');
    const prevBtn = overlay.querySelector('[data-image-lightbox-prev]');
    const nextBtn = overlay.querySelector('[data-image-lightbox-next]');
    const closeBtn = overlay.querySelector('[data-image-lightbox-close]');

    let i18n = {};
    try {
        i18n = JSON.parse(overlay.dataset.i18n || '{}');
    } catch (error) {
        i18n = {};
    }

    /** @type {Array<LightboxEntry>} */
    let entries = [];
    let index = 0;
    let lastFocus = null;
    const multiple = () => entries.length > 1;

    const show = (i) => {
        index = (i + entries.length) % entries.length;
        const entry = entries[index];
        view.src = entry.src;
        view.alt = entry.alt;
        // Menu items carry name/price/description; other images (guides) only show their alt text.
        const hasDetails = Boolean(details && entry.title);
        details?.classList.toggle('hidden', !hasDetails);
        caption.classList.toggle('hidden', hasDetails);
        caption.textContent = hasDetails ? '' : entry.alt;
        if (hasDetails) {
            titleEl.textContent = entry.title;
            priceEl.textContent = entry.price;
            priceEl.classList.toggle('hidden', entry.price === '');
            descEl.textContent = entry.description;
            descEl.classList.toggle('hidden', entry.description === '');
        }
        counter.textContent = multiple()
            ? (i18n.counter || ':current / :total').replace(':current', String(index + 1)).replace(':total', String(entries.length))
            : '';
    };

    const isOpen = () => !overlay.classList.contains('hidden');

    const close = () => {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        document.body.style.overflow = '';
        lastFocus?.focus?.();
    };

    prevBtn.addEventListener('click', () => show(index - 1));
    nextBtn.addEventListener('click', () => show(index + 1));
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close();
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen()) return;
        if (event.key === 'Escape') {
            // Keep a modal underneath (e.g. the cart) open: Esc only closes the viewer.
            event.stopImmediatePropagation();
            close();
        } else if (event.key === 'ArrowLeft' && multiple()) show(index - 1);
        else if (event.key === 'ArrowRight' && multiple()) show(index + 1);
    }, true);

    let touchStartX = null;
    overlay.addEventListener('touchstart', (event) => {
        touchStartX = event.touches[0]?.clientX ?? null;
    }, { passive: true });
    overlay.addEventListener('touchend', (event) => {
        if (touchStartX === null || !multiple()) return;
        const deltaX = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
        touchStartX = null;
        if (Math.abs(deltaX) > 50) show(index + (deltaX < 0 ? 1 : -1));
    }, { passive: true });

    viewer = {
        open(list, i) {
            if (list.length === 0) return;
            entries = list;
            lastFocus = document.activeElement;
            prevBtn.classList.toggle('hidden', !multiple());
            nextBtn.classList.toggle('hidden', !multiple());
            show(i);
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            document.body.style.overflow = 'hidden';
            closeBtn.focus();
        },
    };

    return viewer;
}

/**
 * Viewer entry of an image. Lazy images may still show their placeholder, so the real URL can be given
 * in data-lightbox-src.
 *
 * @param {HTMLImageElement} img Image element.
 * @returns {LightboxEntry} Entry.
 */
function toEntry(img) {
    return {
        src: img.dataset.lightboxSrc || img.currentSrc || img.src,
        alt: img.alt || '',
        title: img.dataset.lightboxTitle || '',
        price: img.dataset.lightboxPrice || '',
        description: img.dataset.lightboxDescription || '',
    };
}

/**
 * Make an image keyboard-focusable and announce it as a button that opens the viewer.
 *
 * @param {HTMLImageElement} img Image element.
 * @returns {void}
 */
function markZoomable(img) {
    img.classList.add('cursor-zoom-in');
    img.setAttribute('tabindex', '0');
    img.setAttribute('role', 'button');
}

/**
 * Guide detail pages: every image inside `.guide-article` opens in the viewer.
 *
 * @returns {void}
 */
export function initGuideLightbox() {
    const images = [...document.querySelectorAll('.guide-article img')];
    if (images.length === 0 || !getViewer()) return;

    images.forEach((img, i) => {
        markZoomable(img);
        const open = (event) => {
            event.preventDefault();
            getViewer().open(images.map(toEntry), i);
        };
        img.addEventListener('click', open);
        img.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') open(event);
        });
    });
}

/**
 * Grouped images rendered at any time (e.g. by Alpine): `<img data-lightbox="group">` opens the viewer with
 * the currently visible, successfully loaded images of the same group, so filtered-out items are skipped.
 *
 * @returns {void}
 */
export function initGroupedImageLightbox() {
    if (!document.querySelector('[data-image-lightbox]')) return;

    const zoomable = (img) => img instanceof HTMLImageElement && !img.hidden && img.getClientRects().length > 0 && toEntry(img).src !== '';
    const openFrom = (img, event) => {
        event.preventDefault();
        event.stopPropagation();
        const group = [...document.querySelectorAll('img[data-lightbox]')]
            .filter((candidate) => candidate.dataset.lightbox === img.dataset.lightbox && zoomable(candidate));
        const index = Math.max(0, group.indexOf(img));
        getViewer()?.open((group.length > 0 ? group : [img]).map(toEntry), group.length > 0 ? index : 0);
    };

    // Capture phase: containers such as the cart modal stop click propagation (@click.stop).
    document.addEventListener('click', (event) => {
        const img = event.target.closest?.('img[data-lightbox]');
        if (img && zoomable(img)) openFrom(img, event);
    }, true);
    document.addEventListener('keydown', (event) => {
        const img = event.target;
        if ((event.key === 'Enter' || event.key === ' ') && img instanceof HTMLImageElement && img.dataset.lightbox && zoomable(img)) {
            openFrom(img, event);
        }
    });
}
