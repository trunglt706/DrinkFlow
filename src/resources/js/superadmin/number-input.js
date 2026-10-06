/**
 * Grouped number inputs of the superadmin forms ([data-number-input], e.g. package price and room limit).
 *
 * The field stays a plain text input posting its own value: digits are regrouped while typing with the
 * separator the server renders (data-group-separator, the one FormatHelper::formatCurrency() uses), and the
 * Form Request strips every non-digit before validating, so the form also works without JavaScript.
 */
const digitsOnly = (value) => String(value ?? '').replace(/\D+/g, '');

const group = (digits, separator) => {
    const trimmed = digits.replace(/^0+(?=\d)/, '');

    return trimmed.replace(/\B(?=(\d{3})+(?!\d))/g, separator);
};

function format(input) {
    const separator = input.dataset.groupSeparator || '.';
    const before = input.value;
    const caret = input.selectionStart ?? before.length;
    // Keep the caret after the same number of digits once the separators move.
    const digitsBeforeCaret = digitsOnly(before.slice(0, caret)).length;
    const formatted = group(digitsOnly(before), separator);
    if (formatted === before) return;

    input.value = formatted;
    if (document.activeElement !== input) return;
    let position = 0;
    for (let seen = 0; position < formatted.length && seen < digitsBeforeCaret; position += 1) {
        if (/\d/.test(formatted[position])) seen += 1;
    }
    input.setSelectionRange(position, position);
}

export function initSuperadminNumberInputs() {
    document.querySelectorAll('[data-number-input]').forEach(format);
    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-number-input]')) {
            format(event.target);
        }
    });
}
