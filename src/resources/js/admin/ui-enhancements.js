import { renderSubmitLoading, restoreSubmitLoading } from '../shared/submit-loading';

/**
 * DrinkFlow Admin UI Enhancements
 * - Debounce Search with Clear ('x') Button
 * - Searchable Select (Combobox with Filter)
 * - Date Range Picker with Presets
 * - Table Skeleton Loading State
 * - Form Submit Loading & Double-Click Prevention
 */

export function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Normalize text for keyword matching: case-insensitive and diacritic-insensitive.
 *
 * @param {*} value Raw text to normalize.
 * @returns {string} Lower-cased text without diacritics (đ/Đ folded to d).
 */
export function normalizeSearchText(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

/**
 * 1. Debounce Search & Clear Button
 */
export function initSearchDebounceAndClear() {
    const searchInputs = document.querySelectorAll('[data-search-input], .admin-search-input');

    searchInputs.forEach(input => {
        if (input.dataset.searchInitialized) return;
        input.dataset.searchInitialized = 'true';

        const wrapper = input.closest('.search-input-wrapper') || input.parentElement;
        let clearBtn = wrapper.querySelector('.search-clear-btn');

        if (!clearBtn && wrapper) {
            clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'search-clear-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface p-0.5 rounded-full hover:bg-surface-container transition-all cursor-pointer hidden';
            clearBtn.setAttribute('aria-label', 'Clear search');
            clearBtn.innerHTML = '<span class="material-symbols-outlined text-[16px] block">close</span>';
            if (wrapper.classList.contains('relative') || getComputedStyle(wrapper).position === 'relative') {
                wrapper.appendChild(clearBtn);
            }
        }

        const toggleClearBtn = () => {
            if (clearBtn) {
                if (input.value.trim().length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
        };

        const triggerFilter = debounce(() => {
            const event = new CustomEvent('admin:search', {
                bubbles: true,
                detail: { query: input.value.trim().toLowerCase() }
            });
            input.dispatchEvent(event);
        }, Number(input.dataset.debounce || 300));

        input.addEventListener('input', () => {
            toggleClearBtn();
            triggerFilter();
        });

        clearBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            toggleClearBtn();
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new CustomEvent('admin:search', {
                bubbles: true,
                detail: { query: '' }
            }));
            input.dispatchEvent(new CustomEvent('admin:search-cleared', {
                bubbles: true
            }));
        });

        toggleClearBtn();
    });
}

/**
 * 2. Searchable Select (Combobox)
 */
export function initSearchableSelects() {
    const selects = document.querySelectorAll('select[data-searchable]');

    selects.forEach(select => {
        if (select.dataset.searchableInitialized) return;
        select.dataset.searchableInitialized = 'true';

        // Hide original select but keep in DOM for form serialization
        select.classList.add('hidden');

        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select-container relative w-full text-xs font-sans';

        const triggerBtn = document.createElement('button');
        triggerBtn.type = 'button';
        triggerBtn.className = 'w-full h-9 px-3 bg-surface border border-outline-variant rounded flex items-center justify-between text-left text-on-surface hover:border-primary focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer';

        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        const triggerText = document.createElement('span');
        triggerText.className = 'truncate font-medium';
        triggerText.textContent = selectedOption ? selectedOption.text : '';

        const triggerIcon = document.createElement('span');
        triggerIcon.className = 'material-symbols-outlined text-[18px] text-outline transition-transform duration-200';
        triggerIcon.textContent = 'arrow_drop_down';

        triggerBtn.appendChild(triggerText);
        triggerBtn.appendChild(triggerIcon);
        wrapper.appendChild(triggerBtn);

        // Dropdown Panel
        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-dropdown absolute left-0 right-0 top-full mt-1 bg-surface-container-lowest border border-outline-variant rounded-lg shadow-xl z-50 p-2 space-y-1.5 hidden max-h-60 flex flex-col backdrop-blur-xs';

        // Search Input inside dropdown
        const searchBox = document.createElement('div');
        searchBox.className = 'relative flex items-center mb-1';
        searchBox.innerHTML = `
            <span class="material-symbols-outlined absolute left-2.5 text-[16px] text-outline">search</span>
            <input type="text" autocomplete="off" class="w-full h-8 pl-8 pr-3 bg-surface border border-outline-variant rounded text-xs text-on-surface outline-none focus:border-primary">
        `;
        // Placeholder/empty text come from Blade (data-placeholder / data-empty-text) so they stay translated.
        searchBox.querySelector('input').placeholder = select.dataset.placeholder || '';
        dropdown.appendChild(searchBox);

        // Options List Container
        const listContainer = document.createElement('div');
        listContainer.className = 'overflow-y-auto flex-1 space-y-0.5 max-h-44 divide-y divide-outline-variant/20';
        dropdown.appendChild(listContainer);

        const renderOptions = (filter = '') => {
            listContainer.innerHTML = '';
            const normalizedFilter = normalizeSearchText(filter);
            let count = 0;

            Array.from(select.options).forEach(opt => {
                // Options may carry extra searchable terms (e.g. email, code) in data-search.
                if (normalizedFilter && !normalizeSearchText(`${opt.text} ${opt.dataset.search || ''}`).includes(normalizedFilter)) return;
                count++;

                const optBtn = document.createElement('button');
                optBtn.type = 'button';
                optBtn.className = `w-full px-2.5 py-1.5 rounded text-left text-xs transition-colors flex items-center justify-between cursor-pointer ${opt.value === select.value ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-surface-container-low text-on-surface'}`;
                optBtn.textContent = opt.text;

                if (opt.value === select.value) {
                    optBtn.insertAdjacentHTML('beforeend', '<span class="material-symbols-outlined text-[16px] text-primary">check</span>');
                }

                optBtn.addEventListener('click', () => {
                    select.value = opt.value;
                    triggerText.textContent = opt.text;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    closeDropdown();
                });

                listContainer.appendChild(optBtn);
            });

            if (count === 0) {
                const emptyEl = document.createElement('div');
                emptyEl.className = 'p-3 text-center text-outline text-xs';
                emptyEl.textContent = select.dataset.emptyText || '';
                listContainer.appendChild(emptyEl);
            }
        };

        const filterInput = searchBox.querySelector('input');
        filterInput.addEventListener('input', () => {
            renderOptions(filterInput.value);
        });

        function openDropdown() {
            dropdown.classList.remove('hidden');
            triggerIcon.classList.add('rotate-180');
            filterInput.value = '';
            renderOptions();
            setTimeout(() => filterInput.focus(), 50);
        }

        function closeDropdown() {
            dropdown.classList.add('hidden');
            triggerIcon.classList.remove('rotate-180');
        }

        triggerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('hidden')) {
                // Close any other opened dropdowns
                document.querySelectorAll('.searchable-dropdown').forEach(d => d.classList.add('hidden'));
                openDropdown();
            } else {
                closeDropdown();
            }
        });

        dropdown.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('click', () => {
            closeDropdown();
        });

        // Sync when select changes programmatically
        select.addEventListener('change', () => {
            const current = select.options[select.selectedIndex];
            if (current) triggerText.textContent = current.text;
        });

        wrapper.appendChild(dropdown);
        select.parentNode.insertBefore(wrapper, select.nextSibling);
    });
}

/**
 * 3. Date Range Filter with Presets
 */
export function initDateRangePickers() {
    const pickers = document.querySelectorAll('[data-date-range-picker], .date-range-picker-container');

    pickers.forEach(container => {
        if (container.dataset.dateRangeInitialized) return;
        container.dataset.dateRangeInitialized = 'true';

        const triggerBtn = container.querySelector('[data-date-range-trigger], .date-range-toggle');
        const dropdown = container.querySelector('[data-date-range-dropdown], .date-range-dropdown');
        const labelEl = container.querySelector('[data-date-range-label], .date-range-label');
        const startInput = container.querySelector('input[name="date_from"], input[data-date-from], .date-from-hidden');
        const endInput = container.querySelector('input[name="date_to"], input[data-date-to], .date-to-hidden');
        const dateFromInput = container.querySelector('.date-from-input, input[data-custom-from]');
        const dateToInput = container.querySelector('.date-to-input, input[data-custom-to]');
        const customBox = container.querySelector('[data-custom-date-box]');
        const btnApply = container.querySelector('[data-apply-date-range], .apply-btn');

        if (!triggerBtn || !dropdown) return;

        function formatDate(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function formatDisplay(d) {
            const parts = d.split('-');
            if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
            return d;
        }

        function setRange(start, end, presetName, labelText) {
            if (startInput) startInput.value = start;
            if (endInput) endInput.value = end;
            if (dateFromInput) dateFromInput.value = start;
            if (dateToInput) dateToInput.value = end;

            if (labelEl) {
                if (start && end) {
                    if (start === end) {
                        labelEl.textContent = start === formatDate(new Date()) ? (labelText || container.dataset.labelToday || '') : `${formatDisplay(start)}`;
                    } else {
                        labelEl.textContent = `${labelText || presetName}: ${formatDisplay(start)} ~ ${formatDisplay(end)}`;
                    }
                } else if (start) {
                    labelEl.textContent = `>= ${formatDisplay(start)}`;
                } else if (end) {
                    labelEl.textContent = `<= ${formatDisplay(end)}`;
                } else {
                    labelEl.textContent = labelText || container.dataset.labelAll || '';
                }
            }

            // Dispatch event
            const event = new CustomEvent('admin:daterange-change', {
                bubbles: true,
                detail: { start, end, preset: presetName }
            });
            container.dispatchEvent(event);
            document.dispatchEvent(event);

            // Close dropdown
            dropdown.classList.add('hidden');
        }

        dropdown.querySelectorAll('[data-preset], .preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const preset = btn.dataset.preset;
                const now = new Date();
                let start = '';
                let end = '';

                if (preset === 'today') {
                    start = formatDate(now);
                    end = formatDate(now);
                } else if (preset === 'yesterday') {
                    const y = new Date(now);
                    y.setDate(now.getDate() - 1);
                    start = formatDate(y);
                    end = formatDate(y);
                } else if (preset === '7days' || preset === 'last_7_days') {
                    const d7 = new Date(now);
                    d7.setDate(now.getDate() - 6);
                    start = formatDate(d7);
                    end = formatDate(now);
                } else if (preset === '30days' || preset === 'last_30_days') {
                    const d30 = new Date(now);
                    d30.setDate(now.getDate() - 29);
                    start = formatDate(d30);
                    end = formatDate(now);
                } else if (preset === 'this_month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    start = formatDate(firstDay);
                    end = formatDate(now);
                } else if (preset === 'last_month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
                    start = formatDate(firstDay);
                    end = formatDate(lastDay);
                } else if (preset === 'all') {
                    start = '';
                    end = '';
                } else if (preset === 'custom') {
                    if (customBox) customBox.classList.remove('hidden');
                    return;
                }

                setRange(start, end, preset, btn.textContent.trim());
            });
        });

        btnApply?.addEventListener('click', () => {
            const start = dateFromInput?.value || startInput?.value || '';
            const end = dateToInput?.value || endInput?.value || '';
            setRange(start, end, 'custom', container.dataset.labelCustom || '');
        });

        triggerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        dropdown.addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => dropdown.classList.add('hidden'));
    });
}


/**
 * 4. Table Skeleton Loading Helper
 */
export function renderTableSkeleton(tbody, cols = 5, rows = 5) {
    if (!tbody) return;
    const skeletonRowsHtml = Array.from({ length: rows }).map(() => `
        <tr class="table-skeleton-row animate-pulse border-b border-outline-variant/30">
            ${Array.from({ length: cols }).map((_, i) => `
                <td class="py-3.5 px-3">
                    <div class="h-4 bg-surface-container rounded ${i === 0 ? 'w-16' : (i === 1 ? 'w-36' : (i === cols - 1 ? 'w-20 mx-auto' : 'w-24'))}"></div>
                </td>
            `).join('')}
        </tr>
    `).join('');
    tbody.innerHTML = skeletonRowsHtml;
}

/**
 * 5. Form Submit Loading & Double-Click Protection
 */
export function initFormSubmitLoading() {
    const forms = document.querySelectorAll('form:not([data-no-loading])');

    forms.forEach(form => {
        if (form.dataset.loadingInitialized) return;
        form.dataset.loadingInitialized = 'true';

        form.addEventListener('submit', function (e) {
            // If already validated and about to submit
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn || submitBtn.disabled) return;

            const originalHtml = submitBtn.innerHTML;
            const loadingText = submitBtn.dataset.loadingText || form.dataset.loadingText || document.body.dataset.processingText || '';

            submitBtn.dataset.originalContent = originalHtml;
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'cursor-not-allowed');

            renderSubmitLoading(submitBtn);

            // Reset after 8s fallback in case of validation error without navigation
            setTimeout(() => {
                if (submitBtn && submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-80', 'cursor-not-allowed');
                    if (submitBtn.dataset.originalContent) {
                        submitBtn.innerHTML = submitBtn.dataset.originalContent;
                    }
                    restoreSubmitLoading(submitBtn);
                }
            }, 8000);
        });
    });
}

/**
 * 6. Admin Sidebar Collapse / Expand System
 */
export function toggleAdminSidebar() {
    const html = document.documentElement;
    const isCollapsed = html.classList.toggle('sidebar-collapsed');
    try {
        localStorage.setItem('df_admin_sidebar_collapsed', isCollapsed ? 'true' : 'false');
    } catch (e) {}
    syncSidebarToggleIcons(isCollapsed);
}

export function syncSidebarToggleIcons(isCollapsed) {
    if (typeof isCollapsed === 'undefined') {
        isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
    }
    document.querySelectorAll('.sidebar-collapse-toggle-icon').forEach(icon => {
        icon.textContent = isCollapsed ? 'dock_to_right' : 'dock_to_left';
    });
}

export function initAdminSidebar() {
    syncSidebarToggleIcons();
}

/**
 * Initialize header notification and workspace switcher dropdowns.
 *
 * These controls intentionally use vanilla JavaScript because the admin bundle
 * does not boot Alpine.js, while their Blade markup is shared by every admin page.
 *
 * @returns {void}
 */
export function initAdminLayoutDropdowns() {
    const dropdowns = [
        {
            root: document.querySelector('[data-admin-workspace-switcher]'),
            toggleSelector: '[data-workspace-toggle]',
            menuSelector: '[data-workspace-menu]',
            chevronSelector: '[data-workspace-chevron]',
        },
        {
            root: document.querySelector('[data-admin-notifications]'),
            toggleSelector: '[data-notifications-toggle]',
            menuSelector: '[data-notifications-menu]',
        },
    ];

    dropdowns.forEach(({ root, toggleSelector, menuSelector, chevronSelector }) => {
        if (!root) return;

        const toggle = root.querySelector(toggleSelector);
        const menu = root.querySelector(menuSelector);
        const chevron = chevronSelector ? root.querySelector(chevronSelector) : null;

        if (!toggle || !menu) return;

        const setOpen = (isOpen) => {
            menu.classList.toggle('hidden', !isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
            chevron?.classList.toggle('rotate-180', isOpen);
        };

        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            setOpen(!isOpen);
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) setOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setOpen(false);
        });
    });

    const notificationsRoot = document.querySelector('[data-admin-notifications]');
    const notificationsMenu = notificationsRoot?.querySelector('[data-notifications-menu]');
    const markAllReadButton = notificationsRoot?.querySelector('[data-mark-all-read]');

    markAllReadButton?.addEventListener('click', async () => {
        const endpoint = notificationsMenu?.dataset.markAllUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!endpoint || !csrfToken) return;

        markAllReadButton.disabled = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!response.ok) throw new Error('Unable to mark notifications as read.');

            notificationsRoot.querySelectorAll('[data-unread-notification]').forEach((item) => item.remove());
            notificationsRoot.querySelector('[data-unread-count]')?.remove();
            notificationsRoot.querySelector('[data-unread-indicator]')?.remove();

            const list = notificationsRoot.querySelector('[data-notifications-menu] .max-h-64');
            if (list && !list.querySelector('[data-unread-notification]')) {
                list.innerHTML = `<div class="px-4 py-6 text-center text-outline text-xs"><span class="material-symbols-outlined text-[28px] text-outline/60 block mx-auto mb-1">notifications_off</span><span>${notificationsMenu.dataset.emptyText || ''}</span></div>`;
            }
        } catch (error) {
            console.error(error);
        } finally {
            markAllReadButton.disabled = false;
        }
    });

    const languageRoots = document.querySelectorAll('[data-admin-language-switcher]');
    languageRoots.forEach((languageRoot) => {
        const languageToggle = languageRoot.querySelector('[data-language-toggle]');
        const languageMenu = languageRoot.querySelector('[data-language-menu]');
        const languageChevron = languageRoot.querySelector('[data-language-chevron]');

        if (languageToggle && languageMenu) {
            const setLanguageMenuOpen = (isOpen) => {
                languageMenu.classList.toggle('hidden', !isOpen);
                languageToggle.setAttribute('aria-expanded', String(isOpen));
                languageChevron?.classList.toggle('rotate-180', isOpen);
            };

            languageToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                setLanguageMenuOpen(languageToggle.getAttribute('aria-expanded') !== 'true');
            });
            document.addEventListener('click', (event) => {
                if (!languageRoot.contains(event.target)) setLanguageMenuOpen(false);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setLanguageMenuOpen(false);
            });
        }
    });
}

/**
 * 7. Currency Input Formatter (data-format-currency="true")
 */
export function initCurrencyFormatters() {
    function formatValue(input) {
        if (!input) return;
        const raw = String(input.value || '').replace(/\D/g, '');
        if (!raw) {
            input.value = '';
            return;
        }
        input.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    const attach = (input) => {
        if (!input || input.dataset.currencyFormatted) return;
        input.dataset.currencyFormatted = 'true';
        input.setAttribute('inputmode', 'numeric');

        formatValue(input);
        input.addEventListener('input', () => formatValue(input));
        input.addEventListener('change', () => formatValue(input));
        input.addEventListener('focus', () => formatValue(input));
        input.addEventListener('blur', () => formatValue(input));
    };

    document.querySelectorAll('input[data-format-currency="true"]').forEach(attach);

    // Dynamic observer for dynamically rendered inputs (modals, x-for templates)
    if (typeof MutationObserver !== 'undefined' && document.body) {
        const observer = new MutationObserver(() => {
            document.querySelectorAll('input[data-format-currency="true"]:not([data-currency-formatted])').forEach(attach);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
}

/**
 * 8. Tooltip ([data-tooltip]) and copy-to-clipboard ([data-copy]) helpers.
 *
 * The tooltip is a single fixed-position element, so it is never clipped by scrollable tables.
 * Copy buttons read the value from data-copy and the messages from data-copied-message / data-copy-failed-message.
 * With data-copy-feedback="icon" a success is shown by the button's icon turning into a check mark for 2 seconds
 * instead of a toast.
 */
export function initTooltipsAndCopy() {
    if (window.__dfTooltipsReady) return;
    window.__dfTooltipsReady = true;

    let tipEl = null;
    const ensureTip = () => {
        if (tipEl) return tipEl;
        tipEl = document.createElement('div');
        tipEl.setAttribute('role', 'tooltip');
        tipEl.className = 'pointer-events-none fixed z-[70] hidden w-max max-w-[220px] whitespace-normal break-words rounded-md bg-slate-900 px-2.5 py-1.5 text-left text-[10px] font-medium leading-snug text-white shadow-lg';
        document.body.appendChild(tipEl);
        return tipEl;
    };

    const showTip = (target) => {
        const text = target.dataset.tooltip;
        if (!text) return;
        const tip = ensureTip();
        tip.textContent = text;
        tip.classList.remove('hidden');
        const rect = target.getBoundingClientRect();
        const w = tip.offsetWidth;
        const h = tip.offsetHeight;
        let left = rect.left + rect.width / 2 - w / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
        let top = rect.top - h - 8;
        if (top < 8) top = rect.bottom + 8;
        tip.style.left = `${left}px`;
        tip.style.top = `${top}px`;
    };

    const hideTip = () => tipEl?.classList.add('hidden');

    document.addEventListener('mouseover', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-tooltip]') : null;
        if (target) showTip(target);
    });
    document.addEventListener('mouseout', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-tooltip]') : null;
        if (target && !target.contains(event.relatedTarget)) hideTip();
    });
    document.addEventListener('focusin', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-tooltip]') : null;
        if (target) showTip(target);
    });
    document.addEventListener('focusout', hideTip);
    window.addEventListener('scroll', hideTip, true);

    const writeClipboard = async (text) => {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        if (!ok) throw new Error('copy failed');
    };

    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-copy]') : null;
        if (!button) return;
        event.preventDefault();
        const value = button.dataset.copy || '';
        try {
            await writeClipboard(value);
            const icon = button.dataset.copyFeedback === 'icon' ? button.querySelector('.material-symbols-outlined') : null;
            if (icon) {
                icon.dataset.copyIcon ??= icon.textContent;
                icon.textContent = 'check';
                button.classList.add('!text-emerald-600');
                clearTimeout(Number(button.dataset.copyTimer));
                button.dataset.copyTimer = String(setTimeout(() => {
                    icon.textContent = icon.dataset.copyIcon;
                    button.classList.remove('!text-emerald-600');
                }, 2000));
            } else {
                window.notify?.(`${button.dataset.copiedMessage || 'Copied'}: ${value}`, 'success');
            }
        } catch (error) {
            window.notify?.(button.dataset.copyFailedMessage || 'Copy failed', 'error');
        }
    });
}

/**
 * Master Initialize all UI Enhancements
 */
export function initUiEnhancements() {
    initSearchDebounceAndClear();
    initSearchableSelects();
    initDateRangePickers();
    initFormSubmitLoading();
    initAdminSidebar();
    initAdminLayoutDropdowns();
    initCurrencyFormatters();
    initTooltipsAndCopy();
}
