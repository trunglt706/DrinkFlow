/**
 * Rating of received order items (order page, room dashboard): a click on a star saves it at once and can be
 * changed until the comment is sent. Sending the comment shows a loading state and its outcome; once sent, the
 * comment becomes text with its time and the rating can no longer be changed. Texts come from data-i18n.
 */
export function initItemRatings() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.querySelectorAll('[data-item-rating]:not([data-locked])').forEach((widget) => {
        let i18n = {};
        try { i18n = JSON.parse(widget.dataset.i18n || '{}'); } catch { i18n = {}; }
        const stars = [...widget.querySelectorAll('[data-rating-star]')];
        const label = widget.querySelector('[data-rating-label]');
        const status = widget.querySelector('[data-rating-status]');
        const commentRow = widget.querySelector('[data-rating-comment-row]');
        const hint = widget.querySelector('[data-rating-comment-hint]');
        const comment = widget.querySelector('[data-rating-comment]');
        const save = widget.querySelector('[data-rating-save]');
        const saveIcon = widget.querySelector('[data-rating-save-icon]');
        const saveLabel = widget.querySelector('[data-rating-save-label]');
        const idleLabel = saveLabel.textContent;
        let current = Number(widget.dataset.stars) || 0;
        let busy = false;

        const paint = (value) => stars.forEach((star) => {
            const on = Number(star.dataset.ratingStar) <= value;
            star.classList.toggle('text-amber-500', on);
            star.classList.toggle('text-slate-300', !on);
            star.setAttribute('aria-checked', Number(star.dataset.ratingStar) === current ? 'true' : 'false');
        });
        const setStatus = (text, ok) => {
            status.textContent = text;
            status.className = 'text-[11px] font-semibold ' + (ok ? 'text-emerald-700' : 'text-red-600');
        };
        const setBusy = (on, withComment) => {
            busy = on;
            stars.forEach((star) => { star.disabled = on; });
            save.disabled = on;
            comment.disabled = on && withComment;
            if (withComment) {
                saveIcon.textContent = on ? 'progress_activity' : 'send';
                saveIcon.classList.toggle('animate-spin', on);
                saveLabel.textContent = on ? (i18n.sending || '') : idleLabel;
                save.setAttribute('aria-busy', on ? 'true' : 'false');
            }
        };

        // The rating is final: stars become plain icons, the comment becomes text with its time.
        const lock = (rating) => {
            stars.forEach((star) => {
                const icon = document.createElement('span');
                icon.className = 'h-7 w-6 inline-flex items-center justify-center ' + (Number(star.dataset.ratingStar) <= rating.stars ? 'text-amber-500' : 'text-slate-300');
                icon.setAttribute('aria-hidden', 'true');
                icon.append(star.firstElementChild);
                star.replaceWith(icon);
            });
            widget.querySelector('[data-rating-stars]')?.removeAttribute('role');
            const box = document.createElement('div');
            box.className = 'mt-1 rounded-lg bg-slate-50 px-2.5 py-1.5';
            box.dataset.ratingCommentText = '';
            const text = document.createElement('p');
            text.className = 'text-xs text-slate-800 break-words';
            text.textContent = `“${rating.comment || ''}”`;
            const time = document.createElement('p');
            time.className = 'mt-0.5 flex items-center gap-1 text-[10px] text-slate-500';
            const lockIcon = document.createElement('span');
            lockIcon.className = 'material-symbols-outlined text-[12px]';
            lockIcon.setAttribute('aria-hidden', 'true');
            lockIcon.textContent = 'lock';
            time.append(lockIcon, document.createTextNode((i18n.sentAt || '').replace('__TIME__', rating.commented_at || '')));
            box.append(text, time);
            commentRow.replaceWith(box);
            hint?.remove();
            widget.dataset.locked = '';
        };

        const submit = async (value, withComment) => {
            if (busy) return;
            setBusy(true, withComment);
            setStatus('', true);
            const body = { stars: value };
            if (withComment) body.comment = comment.value.trim();
            try {
                const response = await fetch(widget.dataset.url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const first = data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
                    throw new Error(first || data.message || i18n.error);
                }
                const rating = data.rating || {};
                current = rating.stars ?? value;
                widget.dataset.stars = String(current);
                paint(current);
                label.textContent = i18n.yourRating || label.textContent;
                setStatus('✓ ' + (data.message || i18n.saved), true);
                if (rating.locked) {
                    lock(rating);
                    return;
                }
                commentRow.hidden = false;
                if (hint) hint.hidden = false;
            } catch (error) {
                paint(current);
                setStatus('✕ ' + (error.message || i18n.error), false);
            } finally {
                if (!widget.hasAttribute('data-locked')) setBusy(false, withComment);
            }
        };

        const sendComment = () => {
            if (current < 1) { setStatus(i18n.starsRequired || '', false); return; }
            if (comment.value.trim() === '') { setStatus(i18n.commentRequired || '', false); comment.focus(); return; }
            submit(current, true);
        };

        stars.forEach((star) => {
            const value = Number(star.dataset.ratingStar);
            star.addEventListener('mouseenter', () => { if (!busy) paint(value); });
            star.addEventListener('mouseleave', () => paint(current));
            star.addEventListener('click', () => submit(value, false));
        });
        save.addEventListener('click', sendComment);
        comment.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); sendComment(); }
        });
    });
}
