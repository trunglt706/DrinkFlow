/**
 * Filter selects that depend on another field of the same form (e.g. superadmin/campaigns: Room depends on Agent).
 *
 * Markup: <select data-depends-on="agent"> whose <option data-parent="…"> carry the parent value. The options are
 * rendered by the server (already limited to the Superadmin's scope); this only hides the ones that do not belong
 * to the chosen parent and clears a selection that no longer matches. Options without data-parent (the "all"
 * entry) always stay. No text is built here.
 */
function sync(select, parent) {
    const value = parent.value;
    let selectedHidden = false;
    [...select.options].forEach((option) => {
        if (option.dataset.parent === undefined) return;
        const hidden = value !== '' && option.dataset.parent !== value;
        option.hidden = hidden;
        option.disabled = hidden;
        if (hidden && option.selected) selectedHidden = true;
    });
    if (selectedHidden) select.value = '';
}

export function initSuperadminDependentSelects() {
    document.querySelectorAll('select[data-depends-on]').forEach((select) => {
        const parent = select.form?.elements.namedItem(select.dataset.dependsOn);
        if (!(parent instanceof HTMLSelectElement)) return;
        sync(select, parent);
        parent.addEventListener('change', () => sync(select, parent));
    });
}
