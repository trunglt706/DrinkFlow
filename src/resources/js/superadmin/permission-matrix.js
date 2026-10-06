/**
 * Permission matrix (superadmin/superadmins/_permission-matrix.blade.php): per-group and global
 * "select all" toggles, with an indeterminate group checkbox while only part of a group is granted.
 * Counter texts come from the fieldset's data-i18n (":checked/:total").
 */
export function initSuperadminPermissionMatrix() {
    document.querySelectorAll('[data-permission-matrix]').forEach((matrix) => {
        if (matrix.dataset.bound) return;
        matrix.dataset.bound = '1';
        const items = () => [...matrix.querySelectorAll('[data-permission-item]')];
        const counter = matrix.querySelector('[data-permission-count]');
        const template = matrix.dataset.countTemplate || ':checked/:total';
        const format = (checked, total) => template.replace(':checked', String(checked)).replace(':total', String(total));

        const refresh = () => {
            matrix.querySelectorAll('[data-permission-group]').forEach((group) => {
                const boxes = [...group.querySelectorAll('[data-permission-item]')];
                const checked = boxes.filter((box) => box.checked).length;
                const toggle = group.querySelector('[data-permission-group-toggle]');
                if (toggle) {
                    toggle.checked = boxes.length > 0 && checked === boxes.length;
                    toggle.indeterminate = checked > 0 && checked < boxes.length;
                }
                const groupCount = group.querySelector('[data-permission-group-count]');
                if (groupCount) groupCount.textContent = `${checked}/${boxes.length}`;
            });
            if (counter) {
                const all = items();
                counter.textContent = format(all.filter((box) => box.checked).length, all.length);
            }
        };

        matrix.addEventListener('change', (event) => {
            const toggle = event.target.closest('[data-permission-group-toggle]');
            if (toggle) {
                toggle.closest('[data-permission-group]')
                    .querySelectorAll('[data-permission-item]')
                    .forEach((box) => { box.checked = toggle.checked; });
            }
            refresh();
        });
        matrix.addEventListener('click', (event) => {
            const button = event.target.closest('[data-permission-toggle-all]');
            if (!button) return;
            const value = button.dataset.permissionToggleAll === '1';
            items().forEach((box) => { box.checked = value; });
            refresh();
        });

        refresh();
    });
}
