/**
 * Small Markdown editing helpers shared by the superadmin release notes editor and the profile bio editor.
 * Rendering stays server-side (App\Support\Helpers\MarkdownHelper), which escapes raw HTML.
 */

// Same word pattern as MarkdownHelper::wordCount(): letters/digits joined by an apostrophe or hyphen.
const WORD_PATTERN = /[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu;

/**
 * Count the words of a text, ignoring Markdown symbols.
 *
 * @param {string} text Text or Markdown source.
 * @returns {number}
 */
export function countWords(text) {
    return (String(text ?? '').match(WORD_PATTERN) || []).length;
}

/**
 * Insert Markdown syntax around the selection (inline formats) or in front of each selected line (block formats).
 *
 * @param {HTMLTextAreaElement} textarea Editor.
 * @param {string} format Toolbar format key.
 * @param {Record<string, string>} i18n Page translations (sample texts).
 */
export function applyFormat(textarea, format, i18n) {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const selected = value.slice(start, end);

    const inline = {
        bold: ['**', '**', i18n.md_bold_sample],
        italic: ['_', '_', i18n.md_italic_sample],
        code: ['`', '`', i18n.md_code_sample],
        link: ['[', '](https://)', i18n.md_link_sample],
    };
    const block = {
        heading: ['## ', i18n.md_heading_sample],
        list: ['- ', i18n.md_list_sample],
        ordered_list: ['1. ', i18n.md_list_sample],
        quote: ['> ', i18n.md_quote_sample],
    };

    let replacement;
    let selectFrom;
    let selectTo;

    if (inline[format]) {
        const [before, after, sample] = inline[format];
        const text = selected || sample;
        replacement = `${before}${text}${after}`;
        selectFrom = start + before.length;
        selectTo = selectFrom + text.length;
    } else if (block[format]) {
        const [prefix, sample] = block[format];
        const lines = (selected || sample).split('\n');
        replacement = lines.map((line, index) => (format === 'ordered_list' ? `${index + 1}. ` : prefix) + line).join('\n');
        // Block syntax only works at the start of a line.
        if (start > 0 && value[start - 1] !== '\n') replacement = `\n${replacement}`;
        selectFrom = start;
        selectTo = start + replacement.length;
    } else {
        return;
    }

    textarea.focus();
    textarea.setRangeText(replacement, start, end, 'end');
    textarea.setSelectionRange(selectFrom, selectTo);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}
