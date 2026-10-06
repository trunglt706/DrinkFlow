import { renderSubmitLoading } from '../shared/submit-loading';

/**
 * Public Loading & Submit Overlay Interactive Module
 */
export function initPublicLoading() {
    const overlay = document.getElementById('public-page-loading');
    const card = document.getElementById('public-loading-card');
    const title = document.getElementById('public-loading-title');
    let autoHideTimer = null;
    let isInternalNavigation = false;

    const defaultTitle = title?.dataset.defaultTitle || '';
    const connectingGoogleTitle = title?.dataset.googleTitle || defaultTitle;
    const submittingTitle = title?.dataset.submitTitle || defaultTitle;

    window.showPublicLoading = function(t) {
        if (!overlay || !card) return;

        if (title) title.textContent = t || defaultTitle;

        overlay.classList.remove('hidden', 'pointer-events-none');
        overlay.classList.add('flex');

        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            overlay.classList.add('opacity-100');
            card.classList.remove('scale-95');
            card.classList.add('scale-100');
        });

        clearTimeout(autoHideTimer);
        autoHideTimer = setTimeout(() => {
            window.hidePublicLoading();
        }, 10000);
    };

    window.hidePublicLoading = function() {
        if (!overlay || !card) return;

        overlay.classList.remove('opacity-100');
        overlay.classList.add('opacity-0');
        card.classList.remove('scale-100');
        card.classList.add('scale-95');

        setTimeout(() => {
            overlay.classList.add('hidden', 'pointer-events-none');
            overlay.classList.remove('flex');
        }, 200);

        clearTimeout(autoHideTimer);
    };

    // Smooth subtle reveal on page readiness
    function onInitialLoad() {
        setTimeout(() => {
            window.hidePublicLoading();
        }, 180);
    }

    if (document.readyState === 'complete') {
        onInitialLoad();
    } else {
        window.addEventListener('load', onInitialLoad);
    }

    // Auto-attach to all forms on submit
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.hasAttribute('data-no-loading')) return;

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        let customTitle = submittingTitle;
        if (submitBtn) {
            if (submitBtn.hasAttribute('data-loading-title')) {
                customTitle = submitBtn.getAttribute('data-loading-title');
            }
        }

        if (submitBtn && !submitBtn.disabled) {
            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-wait');
            renderSubmitLoading(submitBtn);
        }
    });

    // Handle Google SSO clicks
    document.addEventListener('click', function(e) {
        const ssoLink = e.target.closest('a[href*="auth/google"], .google-sso-link, .btn-google-sso-direct');
        if (ssoLink) {
            window.showPublicLoading(connectingGoogleTitle);
        }
    });

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const href = link.getAttribute('href') || '';
        if (link.target === '_blank' || link.hasAttribute('download') || href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

        const destination = new URL(link.href, window.location.href);
        isInternalNavigation = destination.origin === window.location.origin && destination.href !== window.location.href;
    });

    // Reset loading on bfcache. Only a page restored from it (event.persisted) can still show the submit
    // state, and only the buttons this script locked (data-original-html) are re-enabled: buttons rendered
    // disabled on purpose (e.g. an action that is not allowed) must stay disabled on a normal load.
    window.addEventListener('pageshow', function(event) {
            window.hidePublicLoading();
            if (!event.persisted) return;
            document.querySelectorAll('button[disabled][data-original-html], a.pointer-events-none').forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-wait', 'pointer-events-none');
                if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                }
            });
    });

    window.addEventListener('beforeunload', () => {
        if (isInternalNavigation) window.showPublicLoading(defaultTitle);
    });
}
