/**
 * Live sponsor draw stage (components/sponsor-draw/stage.blade.php) on the admin draw page and the members'
 * landing page. The backend is the only source of truth: this script renders its state, plays the reel
 * animation for a result the backend already stored, follows the session's realtime channel (draw:{id})
 * and, when a pushed event is missing (sequence gap, reconnect), reloads the state and the events after the
 * last sequence it applied. Admins only send "draw next turn"; no winner ever comes from the browser.
 */
// Wheel motion: a steady free spin while the backend picks the winner, then one smooth deceleration to a stop.
// The settle easing starts at FREE_DEG_PER_MS (its initial slope is 0.6 / 0.2 = 3), so the hand-over has no jump.
const FREE_TURN_MS = 520;
const FREE_DEG_PER_MS = 360 / FREE_TURN_MS;
const SETTLE_MS = 4200;
const SETTLE_EASING = 'cubic-bezier(0.2, 0.6, 0.35, 1)';
const SETTLE_DEGREES = (FREE_DEG_PER_MS * SETTLE_MS) / 3;
const parse = (value, fallback) => {
    try {
        return JSON.parse(value || '');
    } catch {
        return fallback;
    }
};
const trans = (text, values = {}) => Object.entries(values).reduce((out, [key, value]) => out.replaceAll(`:${key}`, String(value)), String(text || ''));

export function initSponsorDrawStage() {
    const root = document.querySelector('[data-sponsor-draw]');
    if (!root || root.dataset.bound) return;
    root.dataset.bound = '1';

    const config = parse(root.dataset.config, {});
    const i18n = parse(root.dataset.i18n, {});
    const isAdmin = config.mode === 'admin';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let state = parse(root.dataset.state, {});
    let lastSequence = Number(state.last_sequence || 0);
    let initialStatus = state.status;
    const panelStatuses = ['completed', 'cancelled', 'confirmed', 'notification_pending', 'notified', 'notification_failed'];
    let socket = null;
    let spinTimer = null;
    let spinning = false;
    let expired = false;
    let busy = false;

    const $ = (selector) => root.querySelector(selector);
    const reel = $('[data-sd-reel]');
    const reelName = $('[data-sd-reel-name]');
    const reelLabel = $('[data-sd-reel-label]');
    const drawButton = $('[data-sd-draw]');

    const setPill = (element, text, tone) => {
        if (!element) return;
        element.dataset.tone = tone || '';
        element.querySelector('span:last-child').textContent = text;
    };
    const banner = (text, tone = 'warn') => {
        const element = $('[data-sd-banner]');
        element.hidden = !text;
        element.textContent = text || '';
        element.dataset.tone = tone;
    };
    // Same timezone as the server-rendered times (the room's), in the page language rather than the browser's.
    const timeOptions = { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric', hourCycle: 'h23' };
    const timeFormat = (() => {
        try {
            return new Intl.DateTimeFormat(config.locale || undefined, { ...timeOptions, timeZone: config.timezone || undefined });
        } catch {
            return new Intl.DateTimeFormat(config.locale || undefined, timeOptions);
        }
    })();
    const formatTime = (iso) => (iso ? timeFormat.format(new Date(iso)) : '—');

    const statusTone = { ready: 'live', drawing: 'live', completed: 'warn', confirmed: 'done', notification_pending: 'warn', notified: 'done', notification_failed: 'bad', cancelled: 'bad' };

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const icon = (name) => {
        const node = element('span', 'material-symbols-outlined sd-slot-icon', name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    };
    const searchInput = $('[data-sd-search]');
    let picking = null;
    let frozenEligible = null;
    const eligibleShown = () => (spinning && frozenEligible ? frozenEligible : state.eligible) || [];

    // Snapshot list: numbered by snapshot position, winners struck through, filtered by the search box.
    function renderPeople() {
        const query = (searchInput?.value || '').trim().toLocaleLowerCase();
        const people = eligibleShown().filter((person) => !query || String(person.name).toLocaleLowerCase().includes(query));
        $('[data-sd-names]').replaceChildren(...people.map((person) => {
            const isPicking = spinning && !person.is_winner && person.name === picking;
            const item = element('li', `sd-person${person.is_winner ? ' is-winner' : ''}${isPicking ? ' is-picking' : ''}`);
            item.append(
                element('span', 'sd-person-no sd-mono', String(Number(person.position) + 1).padStart(2, '0')),
                element('span', 'sd-person-name', person.name),
            );
            if (person.is_winner) item.append(element('span', 'sd-tag sd-mono', i18n.already_won));
            else if (isPicking) item.append(element('span', 'sd-tag sd-mono', i18n.picking));
            return item;
        }));
        $('[data-sd-names-empty]').hidden = people.length > 0;
    }

    // One slot per sponsor to draw: drawn (latest highlighted), the turn being drawn, then locked turns.
    function renderSlots() {
        const results = state.results || [];
        const total = Math.max(Number(state.target_winner_count || 0), results.length);
        const open = ['ready', 'drawing'].includes(state.status) && !expired;
        const slots = [];
        for (let order = 1; order <= total; order++) {
            const result = results[order - 1];
            const isLatest = result && order === results.length && !spinning;
            const isCurrent = !result && order === results.length + 1 && (spinning || busy);
            const slot = element('li', `sd-slot${isLatest ? ' is-latest' : ''}${isCurrent ? ' is-current' : ''}${!result && !isCurrent ? ' is-pending' : ''}`);
            const body = element('div', 'sd-slot-body');
            if (result) {
                body.append(
                    element('span', 'sd-slot-name', result.name),
                    element('span', 'sd-slot-meta sd-mono', isLatest ? i18n.slot_latest : trans(i18n.drawn_meta, { time: formatTime(result.drawn_at), admin: result.drawn_by || '—' })),
                );
            } else {
                body.append(element('span', 'sd-slot-name sd-mono', isCurrent ? i18n.slot_spinning : (open ? i18n.slot_pending : i18n.slot_closed)));
            }
            const tail = result ? icon(isLatest ? 'workspace_premium' : 'check_circle') : (isCurrent ? element('span', 'sd-ping') : icon('lock'));
            slot.append(element('span', 'sd-slot-no sd-mono', `#${order}`), body, tail);
            slots.push(slot);
        }
        $('[data-sd-results]').replaceChildren(...slots);
    }

    // Decorative names around the wheel: members still in the running (never a winner pick from the browser).
    function renderDiscNames() {
        const pool = (state.eligible || []).filter((person) => !person.is_winner).map((person) => person.name);
        const step = Math.max(1, Math.floor(pool.length / 4));
        root.querySelectorAll('[data-sd-disc-name]').forEach((node, index) => {
            // Spread distinct names over the four positions; fewer names leave positions empty instead of repeating.
            node.textContent = index < pool.length ? pool[(index * step) % pool.length] : '';
        });
    }

    searchInput?.addEventListener('input', renderPeople);

    function render() {
        // While the wheel turns the new result stays hidden (list, slots, struck names); spin() renders once it stops.
        if (spinning) return;
        setPill($('[data-sd-status]'), (i18n.status || {})[state.status] || state.status, statusTone[state.status]);
        const results = state.results || [];
        const total = Number(state.target_winner_count || 0);
        const percent = total ? Math.round((state.drawn_count / total) * 100) : 0;
        $('[data-sd-eligible]').textContent = state.eligible_count;
        $('[data-sd-drawn]').textContent = state.drawn_count;
        $('[data-sd-hub-drawn]').textContent = state.drawn_count;
        $('[data-sd-remaining]').textContent = state.remaining_count;
        $('[data-sd-expires]').textContent = formatTime(state.link_expires_at);
        $('[data-sd-progress]').style.width = `${percent}%`;
        $('[data-sd-completion]').textContent = trans(i18n.completion, { percent });
        $('[data-sd-eligible-count]').textContent = trans(i18n.people, { count: state.eligible_count });
        $('[data-sd-remaining-people]').textContent = trans(i18n.remaining_people, { count: (state.eligible || []).filter((person) => !person.is_winner).length });
        $('[data-sd-turn]').textContent = trans(i18n.turn, { current: Math.min(results.length + (results.length < total ? 1 : 0), total), total });

        renderPeople();
        renderSlots();
        renderDiscNames();

        if (!spinning) {
            const last = (state.results || [])[state.results.length - 1];
            reelName.textContent = last ? last.name : '—';
            reelLabel.textContent = last ? trans(i18n.reel_last, { order: last.draw_order }) : i18n.reel_idle;
            reel.classList.toggle('is-won', Boolean(last));
        }

        if (state.status === 'completed') banner(i18n.waiting_confirm, 'warn');
        else if (state.status === 'cancelled') banner(i18n.cancelled, 'bad');
        else if (['confirmed', 'notified', 'notification_pending', 'notification_failed'].includes(state.status)) banner(i18n.confirmed, 'ok');
        else if (!expired) banner('');

        if (drawButton) {
            drawButton.disabled = expired || busy || spinning || !['ready', 'drawing'].includes(state.status) || state.remaining_count <= 0;
        }

        // The admin's confirm / resend / cancel panels are server-rendered for the status at page load:
        // once the session moves on (last turn drawn, cancelled, confirmed elsewhere), reload to show the right ones.
        if (isAdmin && !spinning && !busy && state.status !== initialStatus && panelStatuses.includes(state.status)) {
            initialStatus = state.status;
            setTimeout(() => window.location.reload(), state.status === 'completed' ? 2500 : 0);
        }
    }

    const disc = reel.querySelector('.sd-disc');
    const reduceMotion = () => Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches);
    let discAngle = 0;
    let freeSpin = null;
    let shuffleSlowdown = null;

    // Visual name shuffle over the members still in the running; it slows down with the wheel once the result is known.
    // The name shown at the end is always the result the backend already stored.
    function shuffleNames() {
        const pool = eligibleShown().filter((person) => !person.is_winner).map((person) => person.name);
        if (reduceMotion() || !pool.length) return;
        let delay = 90;
        if (shuffleSlowdown) {
            const progress = (performance.now() - shuffleSlowdown) / SETTLE_MS;
            if (progress >= 0.88) return;
            delay = 90 + 420 * progress * progress;
        }
        picking = pool[Math.floor(Math.random() * pool.length)];
        reelName.textContent = picking;
        renderPeople();
        spinTimer = setTimeout(shuffleNames, delay);
    }

    /** Start turning right away (admin click, or the draw.started event) while the backend picks the winner. */
    function startSpin(order) {
        if (spinning) return;
        frozenEligible = state.eligible || [];
        spinning = true;
        shuffleSlowdown = null;
        reel.classList.remove('is-won');
        reel.classList.add('is-spinning');
        reelLabel.textContent = trans(i18n.reel_spinning, { order });
        renderSlots();
        if (drawButton) drawButton.disabled = true;
        if (!reduceMotion() && disc?.animate) {
            freeSpin = disc.animate([{ transform: `rotate(${discAngle}deg)` }, { transform: `rotate(${discAngle + 360}deg)` }], { duration: FREE_TURN_MS, iterations: Infinity });
        }
        clearTimeout(spinTimer);
        shuffleNames();
    }

    /** Current angle of the free spin, so the deceleration continues from exactly where the wheel is. */
    function currentAngle() {
        if (!freeSpin) return discAngle;
        const elapsed = Number(freeSpin.currentTime || 0);
        return discAngle + ((elapsed % FREE_TURN_MS) / FREE_TURN_MS) * 360;
    }

    /** Decelerate to a stop and reveal the stored winner; resolves once the wheel has stopped. */
    function spin(finalName, order) {
        startSpin(order);
        const from = currentAngle();
        freeSpin?.cancel();
        freeSpin = null;
        const duration = reduceMotion() ? 300 : SETTLE_MS;
        if (!reduceMotion() && disc?.animate) {
            discAngle = from + SETTLE_DEGREES;
            disc.animate([{ transform: `rotate(${from}deg)` }, { transform: `rotate(${discAngle}deg)` }], { duration, easing: SETTLE_EASING, fill: 'forwards' });
        }
        shuffleSlowdown = performance.now();

        return new Promise((resolve) => {
            setTimeout(() => {
                clearTimeout(spinTimer);
                spinning = false;
                picking = null;
                frozenEligible = null;
                shuffleSlowdown = null;
                reel.classList.remove('is-spinning');
                render();
                if (finalName) {
                    reelName.textContent = finalName;
                    reelLabel.textContent = trans(i18n.reel_winner, { order });
                    reel.classList.add('is-won');
                }
                resolve();
            }, duration);
        });
    }

    /** Stop a free spin that never got its result (the draw request failed). */
    function stopSpin() {
        if (!spinning) return;
        if (freeSpin) {
            discAngle = currentAngle();
            freeSpin.cancel();
            freeSpin = null;
            disc?.animate([{ transform: `rotate(${discAngle}deg)` }, { transform: `rotate(${discAngle}deg)` }], { duration: 1, fill: 'forwards' });
        }
        clearTimeout(spinTimer);
        spinning = false;
        picking = null;
        frozenEligible = null;
        reel.classList.remove('is-spinning');
        render();
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (response.status === 410 || payload.reason === 'expired') {
            showExpired();
            throw new Error(payload.message || i18n.expired);
        }
        if (!response.ok) {
            const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(firstError || payload.message || `HTTP ${response.status}`);
        }

        return payload.data;
    }

    async function resync() {
        if (!config.urls.state || expired) return;
        try {
            const data = await request(`${config.urls.state}?after=${lastSequence}`);
            state = data.state;
            lastSequence = Math.max(lastSequence, Number(state.last_sequence || 0));
            render();
        } catch (error) {
            if (!expired) banner(error.message, 'bad');
        }
    }

    async function onEvent(event) {
        if (!event || Number(event.session_id) !== Number(state.session_id)) return;
        const sequence = Number(event.sequence || 0);
        if (sequence <= lastSequence) return;
        if (sequence !== lastSequence + 1) {
            // A push was missed: reload the state (and events) from the last applied sequence.
            await resync();
            return;
        }
        lastSequence = sequence;
        // The admin who clicked animates from the draw response; every other screen follows the events.
        if (event.type === 'draw.started' && !busy) startSpin(event.data?.draw_order);
        if (event.type === 'draw.completed' && !busy) {
            await spin(event.data?.winner, event.data?.draw_order);
        }
        if (['session.cancelled', 'session.expired'].includes(event.type) && !isAdmin) {
            await resync();
            return;
        }
        await resync();
    }

    async function connect() {
        if (!config.urls.token || typeof window.io !== 'function' || expired) {
            setPill($('[data-sd-connection]'), i18n.connection_offline, 'bad');
            return;
        }
        try {
            const { token } = await request(config.urls.token);
            socket?.disconnect();
            socket = window.io(config.realtime, { auth: { token }, transports: ['websocket', 'polling'], reconnection: true });
            socket.on('connect', () => { setPill($('[data-sd-connection]'), i18n.connection_live, 'live'); resync(); });
            socket.on('disconnect', () => { if (!expired) setPill($('[data-sd-connection]'), i18n.connection_resync, 'warn'); });
            // The token never outlives the link: ask for a fresh one when it is refused on reconnect.
            socket.on('connect_error', () => { if (!expired) { setPill($('[data-sd-connection]'), i18n.connection_resync, 'warn'); setTimeout(connect, 5000); socket.disconnect(); } });
            socket.on('sponsor_draw.event', onEvent);
        } catch {
            if (!expired) setPill($('[data-sd-connection]'), i18n.connection_resync, 'warn');
        }
    }

    function showExpired() {
        if (expired) return;
        expired = true;
        socket?.disconnect();
        setPill($('[data-sd-connection]'), i18n.connection_offline, 'bad');
        banner(isAdmin ? i18n.expired_admin : i18n.expired, 'bad');
        if (!isAdmin) {
            // Members lose access entirely: nothing of the session stays on screen.
            root.replaceChildren(Object.assign(document.createElement('div'), { className: 'sd-banner', textContent: i18n.expired }));
            root.firstChild.dataset.tone = 'bad';
        }
        render();
    }

    drawButton?.addEventListener('click', async () => {
        if (busy || spinning || !config.urls.draw) return;
        busy = true;
        render();
        // Turn immediately: the wheel keeps spinning while the backend picks and stores the winner.
        startSpin((state.results || []).length + 1);
        try {
            const data = await request(config.urls.draw, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
            state = data.state;
            lastSequence = Math.max(lastSequence, Number(state.last_sequence || 0));
            await spin(data.name, data.draw_order);
        } catch (error) {
            stopSpin();
            if (!expired) banner(error.message, 'bad');
        } finally {
            busy = false;
            render();
        }
    });

    // The link expiry is enforced by every API call; the timer only switches the page over on time.
    const expiresAt = state.link_expires_at ? new Date(state.link_expires_at).getTime() : 0;
    if (!state.link_valid || (expiresAt && expiresAt <= Date.now())) showExpired();
    else if (expiresAt) setTimeout(showExpired, Math.min(expiresAt - Date.now() + 500, 2147483000));

    render();
    connect();
}
