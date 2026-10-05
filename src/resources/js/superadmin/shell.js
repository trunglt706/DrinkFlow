/**
 * Superadmin app shell: theme (light / dark / system), dropdown menus, mobile sidebar drawer, sidebar menu filter,
 * command palette (Ctrl/⌘ K) and toasts.
 *
 * Markup: resources/views/superadmin/layout.blade.php. The theme is applied before first paint by an inline script in
 * the layout head; this module keeps it in sync afterwards.
 */
const THEME_KEY = 'df_superadmin_theme';
const THEME_ICONS = { light: 'light_mode', dark: 'dark_mode', system: 'contrast' };

const readThemePreference = () => {
    try {
        return localStorage.getItem(THEME_KEY) || 'system';
    } catch (e) {
        return 'system';
    }
};

const prefersDark = () => window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;

/**
 * Apply a theme preference to <html data-theme> and reflect it in the theme menu.
 *
 * @param {'light'|'dark'|'system'} preference Chosen theme.
 */
const applyTheme = (preference) => {
    const root = document.documentElement;
    const dark = preference === 'dark' || (preference === 'system' && prefersDark());
    root.dataset.theme = dark ? 'dark' : 'light';
    root.dataset.themePref = preference;
    document.querySelectorAll('[data-sa-theme]').forEach((item) => {
        item.setAttribute('aria-checked', item.dataset.saTheme === preference ? 'true' : 'false');
    });
    document.querySelectorAll('[data-sa-theme-icon]').forEach((icon) => {
        icon.textContent = THEME_ICONS[preference] || THEME_ICONS.system;
    });
    document.dispatchEvent(new CustomEvent('sa:themechange', { detail: { theme: root.dataset.theme } }));
};

const initTheme = () => {
    applyTheme(readThemePreference());
    document.addEventListener('click', (event) => {
        const item = event.target.closest('[data-sa-theme]');
        if (!item) return;
        try {
            localStorage.setItem(THEME_KEY, item.dataset.saTheme);
        } catch (e) {
            // Storage unavailable: the choice still applies to this page view.
        }
        applyTheme(item.dataset.saTheme);
    });
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
        if (readThemePreference() === 'system') applyTheme('system');
    });
};

/**
 * Generic dropdowns: [data-sa-dropdown] > [data-sa-dropdown-toggle] + [data-sa-dropdown-menu].
 * Used by the topbar menus and by table row actions (components/superadmin/row-actions.blade.php).
 */
const initDropdowns = () => {
    const closeAll = (except = null) => {
        document.querySelectorAll('[data-sa-dropdown]').forEach((dropdown) => {
            if (dropdown === except) return;
            const menu = dropdown.querySelector('[data-sa-dropdown-menu]');
            if (menu && !menu.hidden) {
                menu.hidden = true;
                dropdown.querySelector('[data-sa-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const menuItems = (menu) => [...menu.querySelectorAll('a[href], button:not([disabled])')];

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-sa-dropdown-toggle]');
        if (toggle) {
            const dropdown = toggle.closest('[data-sa-dropdown]');
            const menu = dropdown?.querySelector('[data-sa-dropdown-menu]');
            if (!menu) return;
            const open = menu.hidden;
            closeAll(dropdown);
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) {
                // Open upwards when there is no room below (e.g. the last rows of a table).
                menu.classList.remove('is-up');
                const rect = menu.getBoundingClientRect();
                if (rect.bottom > window.innerHeight - 8 && rect.height < toggle.getBoundingClientRect().top) menu.classList.add('is-up');
            }
            return;
        }
        // Picking an item closes the menu; clicks elsewhere close every menu.
        const inMenu = event.target.closest('[data-sa-dropdown-menu]');
        if (inMenu && event.target.closest('a[href], button')) {
            closeAll();
            return;
        }
        if (!inMenu) closeAll();
    });

    document.addEventListener('keydown', (event) => {
        const menu = document.activeElement?.closest?.('[data-sa-dropdown-menu]')
            || document.activeElement?.closest?.('[data-sa-dropdown]')?.querySelector('[data-sa-dropdown-menu]:not([hidden])');
        if (event.key === 'Escape') {
            const openDropdown = document.activeElement?.closest?.('[data-sa-dropdown]');
            closeAll();
            openDropdown?.querySelector('[data-sa-dropdown-toggle]')?.focus();
            return;
        }
        if (!menu || menu.hidden || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
        event.preventDefault();
        const items = menuItems(menu);
        const index = items.indexOf(document.activeElement);
        const next = event.key === 'ArrowDown' ? (index + 1) % items.length : (index - 1 + items.length) % items.length;
        items[next]?.focus();
    });
};

/** Mobile sidebar drawer with backdrop, Escape to close and focus kept inside while open. */
const initDrawer = () => {
    const sidebar = document.getElementById('superadmin-sidebar');
    const backdrop = document.querySelector('.sa-sidebar-backdrop');
    const opener = document.querySelector('[data-sa-drawer-open]');
    if (!sidebar || !opener) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        if (backdrop) backdrop.hidden = !open;
        opener.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('sa-no-scroll', open);
        if (open) sidebar.querySelector('a, input, button')?.focus();
        else opener.focus();
    };

    opener.addEventListener('click', () => setOpen(true));
    document.querySelectorAll('[data-sa-drawer-close]').forEach((el) => el.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (!sidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') {
            setOpen(false);
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...sidebar.querySelectorAll('a[href], button, input')].filter((el) => el.offsetParent !== null);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
};

const normalize = (value) => String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');

/** Sidebar "filter menu" box: hides links (and empty groups) that do not match. */
const initNavFilter = () => {
    const input = document.querySelector('[data-sa-nav-filter]');
    if (!input) return;
    const empty = document.querySelector('[data-sa-nav-empty]');
    input.addEventListener('input', () => {
        const query = normalize(input.value.trim());
        let shown = 0;
        document.querySelectorAll('[data-sa-nav-group]').forEach((group) => {
            let groupShown = 0;
            group.querySelectorAll('[data-sa-nav-link]').forEach((link) => {
                const match = query === '' || normalize(link.textContent).includes(query);
                link.hidden = !match;
                if (match) groupShown += 1;
            });
            group.hidden = groupShown === 0;
            shown += groupShown;
        });
        if (empty) empty.hidden = shown > 0;
    });
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const first = document.querySelector('[data-sa-nav-link]:not([hidden])');
        if (first) window.location.href = first.href;
    });
};

/** Command palette: Ctrl/⌘ K or the topbar search button opens a filterable list of menu pages. */
const initCommandPalette = () => {
    const palette = document.querySelector('[data-sa-command]');
    if (!palette) return;
    const input = palette.querySelector('[data-sa-command-input]');
    const empty = palette.querySelector('[data-sa-command-empty]');
    const items = [...palette.querySelectorAll('[data-sa-command-item]')];
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    document.querySelectorAll('[data-sa-command-kbd]').forEach((kbd) => { kbd.textContent = isMac ? '⌘ K' : 'Ctrl K'; });
    let activeIndex = 0;
    let lastFocus = null;

    const visibleItems = () => items.filter((item) => !item.hidden);
    const highlight = (index) => {
        const visible = visibleItems();
        activeIndex = visible.length === 0 ? 0 : (index + visible.length) % visible.length;
        items.forEach((item) => item.setAttribute('aria-selected', 'false'));
        const current = visible[activeIndex];
        if (current) {
            current.setAttribute('aria-selected', 'true');
            current.scrollIntoView({ block: 'nearest' });
        }
    };
    const filter = () => {
        const query = normalize(input.value.trim());
        items.forEach((item) => { item.hidden = query !== '' && !normalize(item.dataset.search).includes(query); });
        palette.querySelectorAll('[data-sa-command-group]').forEach((group) => {
            group.hidden = !group.querySelector('[data-sa-command-item]:not([hidden])');
        });
        empty.hidden = visibleItems().length > 0;
        highlight(0);
    };
    const open = () => {
        lastFocus = document.activeElement;
        palette.hidden = false;
        document.body.classList.add('sa-no-scroll');
        input.value = '';
        filter();
        input.focus();
    };
    const close = () => {
        palette.hidden = true;
        document.body.classList.remove('sa-no-scroll');
        lastFocus?.focus?.();
    };

    document.querySelectorAll('[data-sa-command-open]').forEach((button) => button.addEventListener('click', open));
    palette.querySelectorAll('[data-sa-command-close]').forEach((el) => el.addEventListener('click', close));
    input.addEventListener('input', filter);
    items.forEach((item) => item.addEventListener('mousemove', () => highlight(visibleItems().indexOf(item))));
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (palette.hidden) open();
            else close();
            return;
        }
        if (palette.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter') {
            const target = visibleItems()[activeIndex];
            if (target) {
                event.preventDefault();
                window.location.href = target.href;
            }
        } else if (event.key === 'Tab') {
            event.preventDefault();
            input.focus();
        }
    });
};

/**
 * Accessible tabs: [data-sa-tablist] > [data-sa-tab="key"] buttons switch the matching [data-sa-tab-panel="key"]
 * (hidden attribute). Arrow keys / Home / End move between tabs.
 */
const initTabs = () => {
    document.querySelectorAll('[data-sa-tablist]').forEach((list) => {
        const tabs = [...list.querySelectorAll('[data-sa-tab]')];
        const select = (tab, focus = false) => {
            tabs.forEach((other) => {
                const active = other === tab;
                other.setAttribute('aria-selected', String(active));
                other.tabIndex = active ? 0 : -1;
                const panel = document.querySelector(`[data-sa-tab-panel="${other.dataset.saTab}"]`);
                if (panel) panel.hidden = !active;
            });
            if (focus) tab.focus();
        };
        tabs.forEach((tab) => tab.addEventListener('click', () => select(tab)));
        list.addEventListener('keydown', (event) => {
            const index = tabs.indexOf(document.activeElement);
            if (index < 0) return;
            const moves = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };
            if (!(event.key in moves)) return;
            event.preventDefault();
            select(tabs[(moves[event.key] + tabs.length) % tabs.length], true);
        });
    });
};

/**
 * Show a toast in the bottom-right corner.
 *
 * @param {string} message Text to show (already translated).
 * @param {'success'|'error'|'info'} [type='success'] Visual tone.
 */
export function showSuperadminToast(message, type = 'success') {
    const region = document.querySelector('[data-sa-toasts]');
    if (!region || !message) return;
    const toast = document.createElement('div');
    toast.className = `sa-toast is-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'check_circle');
    const text = document.createElement('span');
    text.textContent = message;
    toast.append(icon, text);
    region.append(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 250);
    }, type === 'error' ? 6000 : 3500);
}

/**
 * Show the success messages of the page-level action notice (#notice, set by the page scripts) as toasts, so the result
 * of an action is visible wherever the page is scrolled. The inline success notice itself is hidden by CSS; errors stay
 * inline (and persistent) next to the content they concern.
 */
const mirrorNoticesAsToasts = () => {
    const notice = document.querySelector('#notice.sa-notice');
    if (!notice) return;
    const observer = new MutationObserver(() => {
        if (!notice.classList.contains('is-visible') || !notice.classList.contains('success') || notice.textContent.trim() === '') return;
        showSuperadminToast(notice.textContent.trim(), 'success');
    });
    observer.observe(notice, { attributes: true, attributeFilter: ['class'], childList: true, characterData: true, subtree: true });
};

export function initSuperadminShell() {
    if (!document.body.classList.contains('superadmin-shell')) return;
    initTheme();
    initDropdowns();
    initDrawer();
    initNavFilter();
    initCommandPalette();
    initTabs();
    mirrorNoticesAsToasts();
    window.showSuperadminToast = showSuperadminToast;
}
