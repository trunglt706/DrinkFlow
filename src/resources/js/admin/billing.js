/**
 * Platform invoice download (/admin/billing): fetch the PDF and save it without leaving the page.
 *
 * The link ([data-invoice-download]) stays a plain attachment link when JavaScript is off. When the
 * server cannot render the PDF it answers 503 JSON, shown as a toast (texts come from data-i18n).
 */
const fileNameFrom = (response, fallback) => {
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="?([^";]+)"?/i);

    return match ? match[1] : fallback || 'invoice.pdf';
};

const notify = (message, type) => {
    if (message && typeof window.notify === 'function') {
        window.notify(message, type);
    }
};

const download = async (link) => {
    if (link.getAttribute('aria-busy') === 'true') return;
    let i18n = {};
    try {
        i18n = JSON.parse(link.dataset.i18n || '{}');
    } catch {
        i18n = {};
    }
    const icon = link.querySelector('[data-invoice-download-icon]');
    link.setAttribute('aria-busy', 'true');
    link.classList.add('opacity-60', 'pointer-events-none');
    if (icon) icon.textContent = 'progress_activity';
    icon?.classList.add('animate-spin');

    try {
        const response = await fetch(link.href, { headers: { Accept: 'application/pdf, application/json' }, credentials: 'same-origin' });
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok || !contentType.includes('application/pdf')) {
            const payload = contentType.includes('application/json') ? await response.json().catch(() => ({})) : {};
            notify(payload.message || i18n.failed || '', 'error');
            return;
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = fileNameFrom(response, link.dataset.fileName);
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
        notify(i18n.failed || '', 'error');
    } finally {
        link.removeAttribute('aria-busy');
        link.classList.remove('opacity-60', 'pointer-events-none');
        if (icon) icon.textContent = 'download';
        icon?.classList.remove('animate-spin');
    }
};

export function initAdminBilling() {
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('[data-invoice-download]') : null;
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
        event.preventDefault();
        download(link);
    });
}
