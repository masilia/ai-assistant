import { SELECTORS } from './selectors.js';

/**
 * Store CKEditor instances keyed by their container element. The WeakMap
 * lets the GC reclaim memory when fields are removed from the DOM.
 *
 * @type {WeakMap<HTMLElement, object>}
 */
const editorInstances = new WeakMap();

/**
 * Returns the CKEditor instance captured for a given .ibexa-field-edit
 * container, or undefined if none has been registered yet.
 */
export function getEditor(fieldEdit) {
    return editorInstances.get(fieldEdit);
}

/**
 * Start listening for CKEditor instance-ready events on the document.
 * CKEditor dispatches on its container element; we listen on document in
 * the capture phase so we can react even if the event has been cancelled
 * downstream.
 */
export function attachCkEditorListener(doc) {
    doc.addEventListener('ibexa-ckeditor:instance-ready', (e) => {
        const container = e.target;
        const fieldEdit = container.closest(SELECTORS.richTextContainer);
        if (fieldEdit && e.detail?.editor) {
            editorInstances.set(fieldEdit, e.detail.editor);
        }
    }, true);
}
