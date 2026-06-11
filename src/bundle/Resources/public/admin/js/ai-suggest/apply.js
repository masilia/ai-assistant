import { APPLY_MODE, SUGGEST_MODE } from '../../components/ai-settings/constants.js';
import { getEditor } from './ckeditor.js';
import { NOVASEO, MATRIX } from './selectors.js';

/**
 * Strip surrounding markdown code fences (```...``` or ```json...```) from
 * an AI response. Single source of truth used by all sanitizers.
 */
export function stripCodeFences(text) {
    if (typeof text !== 'string') return '';
    let t = text.trim();
    if (t.startsWith('```')) {
        t = t.replace(/^```(json)?/i, '').replace(/```$/, '').trim();
    }
    return t;
}

/**
 * Read a novaseometas row into its meta key and editable input.
 *
 * @returns {{ metaKey: string, contentInput: HTMLElement|null }}
 */
export function readNovaseoRow(row) {
    const hiddenName = row.querySelector(NOVASEO.nameInput);
    return {
        metaKey: hiddenName?.value || '',
        contentInput: row.querySelector(NOVASEO.contentInput) || null,
    };
}

/**
 * Strip markdown code fences, leading/trailing quotes, and common
 * "value:" prefixes from an AI response.
 */
export function sanitizeAiText(text) {
    let t = stripCodeFences(text);
    t = t.replace(/^["'`]+|["'`]+$/g, '').trim();
    t = t.replace(/^(value|content|answer)\s*[:=]\s*/i, '').trim();
    return t;
}

/**
 * Try to extract a single meta value from an AI response. Handles:
 * - Plain text → returned as-is after sanitization
 * - JSON object → extract the value for `metaKey` if present; otherwise
 *   fall back to the full text (the AI may have returned a single string
 *   instead of a JSON object, or the key may be missing from a partial
 *   response).
 *
 * @returns {string|null}
 */
export function extractSubFieldValue(suggestion, metaKey) {
    const text = stripCodeFences(suggestion);

    try {
        const data = JSON.parse(text);
        if (data && typeof data === 'object' && !Array.isArray(data)) {
            if (metaKey && data[metaKey] !== undefined && data[metaKey] !== null) {
                return sanitizeAiText(String(data[metaKey]));
            }
            // Key not found in JSON — fall back to the full text rather
            // than returning null, so partial responses still produce output.
        }
    } catch (e) {
        // Not JSON, treat as plain text
    }

    return sanitizeAiText(text);
}

/**
 * Apply AI-generated content to a field.
 * @returns {{ success: true } | { success: false, error: string }}
 */
export function applyToField(fieldEdit, fieldType, targetElement, suggestion, mode, applyMode) {
    if (fieldType === 'novaseometas' && applyMode === APPLY_MODE.WHOLE_BLOCK) {
        let data;
        try {
            data = JSON.parse(stripCodeFences(suggestion));
        } catch (e) {
            console.error('[AI] Failed to parse SEO metas JSON:', e, suggestion);
            return { success: false, error: 'AI returned an invalid response. Please try again.' };
        }

        // Resolve target inputs by meta key using the same row contract as
        // everywhere else (no fragile [value="..."] attribute matching).
        const inputsByKey = new Map();
        fieldEdit.querySelectorAll(NOVASEO.row).forEach((row) => {
            const { metaKey, contentInput } = readNovaseoRow(row);
            if (metaKey && contentInput) {
                inputsByKey.set(metaKey.toLowerCase(), contentInput);
            }
        });

        let applied = 0;
        for (const [key, val] of Object.entries(data)) {
            const input = inputsByKey.get(String(key).toLowerCase());
            if (!input) continue;

            const text = (val === null || val === undefined) ? '' : String(val);
            if (mode === SUGGEST_MODE.REPLACE) {
                input.value = text;
            } else {
                // Append without a space separator — SEO values like
                // titles and descriptions should not have spaces between
                // the old and new text.
                input.value = (input.value ? input.value + text : '') || text;
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            applied++;
        }
        return applied > 0
            ? { success: true }
            : { success: false, error: 'No matching SEO fields were found to update.' };
    }

    if (fieldType === 'novaseometas' && applyMode === APPLY_MODE.SUB_FIELD) {
        if (!targetElement) {
            return { success: false, error: 'No target input found for this meta field.' };
        }

        // Resolve the meta key from the sibling hidden name input
        const wrapper = targetElement.closest(NOVASEO.row);
        const hiddenName = wrapper?.querySelector(NOVASEO.nameInput);
        const metaKey = hiddenName?.value || '';

        const extracted = extractSubFieldValue(suggestion, metaKey);
        if (!extracted) {
            return { success: false, error: 'AI returned an empty response. Please try again.' };
        }

        if (mode === SUGGEST_MODE.REPLACE) {
            targetElement.value = extracted;
        } else {
            targetElement.value = (targetElement.value ? targetElement.value + extracted : '') || extracted;
        }
        targetElement.dispatchEvent(new Event('input', { bubbles: true }));
        return { success: true };
    }

    if (fieldType === 'ezmatrix') {
        let data;
        try {
            data = JSON.parse(stripCodeFences(suggestion));
        } catch (e) {
            console.error('[AI] Failed to parse matrix JSON:', e, suggestion);
            return { success: false, error: 'AI returned an invalid matrix response. Please try again.' };
        }

        if (!data || !Array.isArray(data.rows)) {
            return { success: false, error: 'AI response missing "rows" array.' };
        }

        // Ibexa's matrix only renders the rows currently bound to the
        // form. If the AI generated more rows than the DOM has, the
        // extra rows would be silently dropped and `applied` could
        // end up at 0 (-> "No matching matrix cells..." error). Grow
        // the matrix first by clicking Ibexa's own Add-row button,
        // which expands the row template and auto-increments
        // [entries][N] indexes for us.
        const addBtn = fieldEdit.querySelector(MATRIX.addEntry);
        let existingRows = fieldEdit.querySelectorAll(MATRIX.rows).length;
        const needed = data.rows.length - existingRows;
        if (needed > 0 && addBtn) {
            for (let k = 0; k < needed; k++) {
                addBtn.click();
            }
        }

        const rows = fieldEdit.querySelectorAll(MATRIX.rows);
        let applied = 0;

        data.rows.forEach((row, i) => {
            if (!rows[i] || !row.cells) return;
            const inputs = rows[i].querySelectorAll('input[type="text"], textarea');
            const inputByCol = new Map();
            // Build a per-column lookup that handles every variant the
            // AI is likely to emit:
            //   - lowercase identifier: "value"            (preferred)
            //   - uppercase identifier:  "VALUE"
            //   - mixed-case identifier:  "Value"
            //   - display name:          "Value" (matches the <th> text)
            //   - display name uppercased by CSS: "VALUE"
            // The map is keyed by both the identifier and every case variant
            // of it, so any spelling the AI produces resolves to the right input.
            const inputByColVariants = new Map();
            const headerCells = fieldEdit.querySelectorAll(MATRIX.columnHeader);
            inputs.forEach((input) => {
                const m = (input.name || '').match(MATRIX.inputNameRe);
                if (!m) return;
                const colId = m[2];
                inputByCol.set(colId, input);
                // Find the matching <th> by data-identifier
                let headerText = null;
                headerCells.forEach((th) => {
                    if (th.getAttribute('data-identifier') === colId) {
                        headerText = th.textContent.trim();
                    }
                });
                // Index the input under every spelling the AI might use
                const variants = new Set([colId, colId.toLowerCase(), colId.toUpperCase()]);
                if (headerText) variants.add(headerText);
                if (headerText) variants.add(headerText.toLowerCase());
                if (headerText) variants.add(headerText.toUpperCase());
                variants.forEach((v) => inputByColVariants.set(v, input));
            });

            const cells = row.cells;
            if (cells && typeof cells === 'object' && !Array.isArray(cells)) {
                for (const [colKey, val] of Object.entries(cells)) {
                    const input = inputByColVariants.get(colKey)
                        ?? inputByColVariants.get(colKey.toLowerCase())
                        ?? inputByColVariants.get(colKey.toUpperCase());
                    if (!input) {
                        console.warn('[AI] Matrix apply: no input for column key', colKey);
                        continue;
                    }
                    const text = sanitizeAiText(String(val));
                    input.value = mode === SUGGEST_MODE.REPLACE ? text : (input.value + ' ' + text);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    applied++;
                }
            } else if (Array.isArray(cells)) {
                // Positional fallback
                inputs.forEach((input, j) => {
                    if (j >= cells.length) return;
                    const text = sanitizeAiText(String(cells[j]));
                    input.value = mode === SUGGEST_MODE.REPLACE ? text : (input.value + ' ' + text);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    applied++;
                });
            }
        });

        return applied > 0
            ? { success: true }
            : { success: false, error: 'No matching matrix cells were found to update.' };
    }

    if (fieldType === 'ezrichtext') {
        const editor = getEditor(fieldEdit);
        if (!editor) {
            console.warn('[AI] No CKEditor instance found for field');
            return { success: false, error: 'No editor instance found for this field.' };
        }
        if (mode === SUGGEST_MODE.REPLACE) {
            editor.setData(suggestion);
        } else {
            const current = editor.getData();
            editor.setData(current + suggestion);
        }
        return { success: true };
    }

    if (fieldType === 'ezimage') {
        // Image generation result: inject the generated image into the file picker
        if (suggestion && typeof suggestion === 'object' && suggestion.imageData) {
            return applyGeneratedImage(fieldEdit, suggestion);
        }

        // Alt text generation: write to the alt text input
        const input = targetElement || fieldEdit.querySelector('.ibexa-field-edit-preview__image-alt .ibexa-data-source__input');
        if (!input) return { success: false, error: 'No alt text input found for this image.' };
        const cleaned = sanitizeAiText(suggestion);
        if (mode === SUGGEST_MODE.REPLACE) {
            input.value = cleaned;
        } else {
            input.value = (input.value ? input.value + cleaned : '') || cleaned;
        }
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return { success: true };
    }

    const input = targetElement || fieldEdit.querySelector('.ibexa-data-source__input');
    if (!input) return { success: false, error: 'No input element found for this field.' };
    const cleaned = sanitizeAiText(suggestion);
    if (mode === SUGGEST_MODE.REPLACE) {
        input.value = cleaned;
    } else {
        input.value += cleaned;
    }
    input.dispatchEvent(new Event('input', { bubbles: true }));
    return { success: true };
}

/**
 * Convert a base64 image to a File and inject it into Ibexa's native
 * file picker. This triggers the same upload flow as a manual drag-and-drop.
 *
 * @param {HTMLElement} fieldEdit  The ezimage field-edit element
 * @param {{ imageData: string, mimeType: string }} imageResult
 * @returns {{ success: boolean, error?: string }}
 */
function applyGeneratedImage(fieldEdit, imageResult) {
    try {
        // Decode base64 to binary
        const base64Data = imageResult.imageData.startsWith('data:')
            ? imageResult.imageData.split(',')[1]
            : imageResult.imageData;
        const binaryString = atob(base64Data);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        // Create a File object
        const mimeType = imageResult.mimeType || 'image/png';
        const ext = mimeType.split('/')[1] || 'png';
        const file = new File([bytes], `ai-generated.${ext}`, { type: mimeType });

        // Find Ibexa's file input (the hidden input that handles file uploads)
        // Ibexa uses a custom upload widget; we need to find the input element
        // and set its files property via a DataTransfer.
        const fileInput = fieldEdit.querySelector('input[type="file"]');
        if (!fileInput) {
            return { success: false, error: 'No file input found for this image field.' };
        }

        // Use DataTransfer to programmatically set files
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;

        // Dispatch change event to trigger Ibexa's upload handler
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));

        return { success: true };
    } catch (err) {
        console.error('[AI] Failed to apply generated image:', err);
        return { success: false, error: 'Failed to process the generated image.' };
    }
}
