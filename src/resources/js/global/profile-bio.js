import { applyFormat, countWords } from '../shared/markdown-format';

/**
 * Profile bio editor on /me/profile (resources/views/user/global/profile.blade.php): Markdown toolbar, live word
 * counter and a save button blocked over the limit. The backend enforces the same limit (App\Rules\MaxWords).
 */
export function initProfileBioEditor() {
    const editor = document.querySelector('[data-bio-editor]');
    if (!editor || editor.dataset.bound) return;
    editor.dataset.bound = '1';

    const textarea = editor.querySelector('textarea');
    const counter = editor.querySelector('[data-bio-counter]');
    const form = editor.closest('form');
    const submit = form?.querySelector('button[type="submit"]');
    const max = Number(editor.dataset.maxWords || 0);
    let i18n = {};
    try {
        i18n = JSON.parse(editor.dataset.i18n || '{}');
    } catch {
        i18n = {};
    }

    const update = () => {
        const words = countWords(textarea.value);
        const over = max > 0 && words > max;
        counter.textContent = String(i18n.counter || ':count / :max').replace(':count', String(words)).replace(':max', String(max));
        counter.classList.toggle('text-rose-600', over);
        counter.classList.toggle('font-semibold', over);
        textarea.setAttribute('aria-invalid', over ? 'true' : 'false');
        if (submit) submit.disabled = over;
    };

    editor.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-md-format]') : null;
        if (button) applyFormat(textarea, button.dataset.mdFormat, i18n);
    });
    textarea.addEventListener('input', update);
    update();
}
