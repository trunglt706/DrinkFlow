/**
 * Superadmin shared helpers: fetch wrapper, formatting and status-pill rendering.
 *
 * Imported by every superadmin module (resources/js/superadmin/*.js and pages/*.js).
 */

/** Minimal JSON fetch wrapper with CSRF header and error normalization, used by every superadmin page. */
export async function dfApi(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        // Marks the call as AJAX so Laravel does not store it as the session's "previous URL" (redirect()->back()).
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    };
    if (options.body && typeof options.body !== 'string') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(url, { ...options, headers });
    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || `HTTP ${response.status}`);
    }
    return response.json();
}

/** Format an integer VND amount the same way as the rest of the app (e.g. "12.000đ"). */
export function money(value) {
    return new Intl.NumberFormat('vi-VN').format(value || 0) + 'đ';
}

const escapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' };

/** Escape a value for safe interpolation into innerHTML-built markup. */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => escapeMap[character] || character);
}

/**
 * Read the translated status labels the layout renders into `data-status-labels` (see
 * resources/views/components/global/layout.blade.php for the same `data-i18n` convention).
 */
function statusLabels() {
    try {
        return JSON.parse(document.body.dataset.statusLabels || '{}');
    } catch {
        return {};
    }
}

/** Render a translated, colored status badge for a given raw status/severity value. */
export function statusPill(value) {
    const labels = statusLabels();
    return `<span class="status-pill status-${escapeHtml(value)}"><span class="status-dot"></span>${escapeHtml(labels[value] || value)}</span>`;
}
