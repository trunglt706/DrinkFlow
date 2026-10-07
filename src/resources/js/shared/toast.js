let initialized = false;
let toastPromise = null;

/** Load React Toastify and mount its container on first use. */
const loadToast = () => {
    toastPromise ??= import('./toast-container').then(({ mountToastContainer }) => mountToastContainer());
    return toastPromise;
};

/** Initialize the shared browser notification helper; React Toastify itself loads on the first toast. */
export function initToastNotifications() {
    if (initialized) return;
    initialized = true;

    window.notify = (message, type = 'info') => loadToast().then((toast) => {
        const method = typeof toast[type] === 'function' ? toast[type] : toast.info;
        return method(String(message ?? ''));
    });

    // Keep legacy inline Blade handlers non-blocking while routing them through Toastify.
    window.alert = (message) => window.notify(message, 'info');
}
