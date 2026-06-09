import { SELECTORS, MATRIX, FIELD_NAME_RE, CONTENT_EDIT_RE, TITLE_IDENTIFIERS } from './selectors.js';
import { getEditor } from './ckeditor.js';
import { getSupportedFields } from './fieldTypes.js';

/**
 * Detect the field type from a .ibexa-field-edit element by walking the
 * CSS-class → identifier map (the map's order is the precedence order).
 */
export function getFieldType(fieldEdit) {
    for (const [cls, type] of Object.entries(getSupportedFields())) {
        if (fieldEdit.classList.contains(cls)) return type;
    }
    return null;
}

/**
 * Get the field label text, with the trailing required-marker stripped.
 */
export function getFieldLabel(fieldEdit) {
    const label = fieldEdit.querySelector(SELECTORS.fieldLabel);
    return label ? label.textContent.trim().replace(/\s*\*$/, '') : '';
}

/**
 * Get the current value of a field (CKEditor data for rich text, raw value
 * for plain inputs). For ezmatrix, returns a JSON string shaped as
 * {"rows": [{"cells": {<colId>: <value>}}, ...]}.
 */
export function getCurrentValue(fieldEdit, fieldType, targetElement) {
    if (fieldType === 'ezrichtext') {
        const editor = getEditor(fieldEdit);
        return editor ? editor.getData() : '';
    }
    if (fieldType === 'ezmatrix') {
        return getCurrentValueMatrix(fieldEdit);
    }
    const input = targetElement || fieldEdit.querySelector(SELECTORS.dataInput);
    return input ? input.value : '';
}

/**
 * Read an ezmatrix field's DOM and return a JSON string capturing the
 * column headers (from <th data-identifier>) and every row's cell values
 * keyed by column identifier (from the input's [entries][<i>][<colId>]
 * name suffix).
 *
 * Returns "{\"rows\":[]}" when the matrix has no rows yet.
 *
 * @returns {string}
 */
export function getCurrentValueMatrix(fieldEdit) {
    const headers = {};
    fieldEdit.querySelectorAll(MATRIX.columnHeader).forEach((th) => {
        const id = th.getAttribute('data-identifier');
        if (!id) return;
        headers[id] = th.textContent.trim();
    });

    const rows = [];
    fieldEdit.querySelectorAll(MATRIX.rows).forEach((tr) => {
        const cells = {};
        tr.querySelectorAll('input[type="text"], textarea').forEach((input) => {
            const m = (input.name || '').match(MATRIX.inputNameRe);
            if (m) cells[m[2]] = input.value || '';
        });
        rows.push({ cells });
    });

    return JSON.stringify({ rows });
}

/**
 * Resolve the content type name from the page header or a data attribute.
 * The Ibexa edit header renders "Editing ContentTypeName".
 */
export function getContentTypeName(doc) {
    const fromData = doc.querySelector('[data-content-type-name]')?.dataset.contentTypeName;
    if (fromData) return fromData;

    const actionNameEl = doc.querySelector(SELECTORS.editHeaderAction);
    if (!actionNameEl) return '';

    const raw = actionNameEl.textContent.replace(/\s+/g, ' ').trim();
    return raw.replace(/^(Editing|Creating|Translating)\s*/i, '').trim();
}

/**
 * Resolve the content title from the title/name field or page heading.
 */
export function getContentTitle(doc) {
    const titleInput =
        doc.querySelector('input[name*="[fieldsData][title][value]"]')
        || doc.querySelector('input[name*="[fieldsData][name][value]"]');
    return titleInput?.value?.trim()
        || doc.querySelector('h1')?.textContent?.trim()
        || '';
}

/**
 * Extract the field identifier (e.g. "description") from an input's name.
 */
export function getFieldIdentifier(input) {
    return input?.name?.match(FIELD_NAME_RE)?.[1] || null;
}

/**
 * Collect sibling field values (one per identifier) to give the AI context.
 * Excludes the field being edited and the title/name fields.
 *
 * @returns {Array<{label: string, value: string}>}
 */
export function getSiblingFields(doc, currentIdentifier) {
    const siblingFields = [];
    const seenIdentifiers = new Set();

    doc.querySelectorAll(
        `input${SELECTORS.dataInput}, textarea${SELECTORS.dataInput}`
    ).forEach((input) => {
        const identifier = getFieldIdentifier(input);
        if (!identifier) return;
        if (identifier === currentIdentifier) return;
        if (TITLE_IDENTIFIERS.has(identifier)) return;
        if (seenIdentifiers.has(identifier)) return;

        const value = input.value?.trim();
        if (!value) return;

        const fieldContainer = input.closest(SELECTORS.fieldEdit);
        const label = fieldContainer?.querySelector(SELECTORS.fieldLabel)?.textContent
            ?.trim().replace(/\s*\*$/, '')
            || identifier;

        seenIdentifiers.add(identifier);
        siblingFields.push({ label, value });
    });

    return siblingFields;
}

/**
 * Resolve the content ID from the form action URL, location, or query string.
 * Returns '' for new (unsaved) content.
 */
export function getContentId(doc) {
    const formAction = doc.querySelector(SELECTORS.form)?.action || '';
    return doc.querySelector('[data-content-id]')?.dataset.contentId
        || formAction.match(CONTENT_EDIT_RE)?.[1]
        || location.pathname.match(CONTENT_EDIT_RE)?.[1]
        || location.pathname.match(/\/(\d+)\//)?.[1]
        || new URL(location.href).searchParams.get('contentId')
        || '';
}
