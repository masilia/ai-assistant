import { SELECTORS, MATRIX } from './selectors.js';
import { getFieldType, getFieldLabel, getCurrentValue, getContentTypeName, getContentTitle, getSiblingFields, getFieldIdentifier, getContentId } from './fieldInfo.js';
import { collectNovaseoMetaKeys, injectNovaseoMetaButtons, createAiButton, createTranslateButton, injectTranslateButtonsForSiblings } from './novaseo.js';
import { applyToField } from './apply.js';
import { APPLY_MODE, SUGGEST_MODE } from '../../components/ai-settings/constants.js';

/**
 * Open the AI suggestion modal for a specific field. Builds the detail
 * payload (field context + onApply callback) and dispatches the custom
 * event that the modal listens for.
 */
function openAiModal(doc, fieldEdit, fieldType, targetElement, subFieldName, applyMode, extra = {}) {
    const currentInput = targetElement || fieldEdit.querySelector(SELECTORS.dataInput);
    const fieldName = subFieldName || getFieldLabel(fieldEdit);
    const resolvedApplyMode = applyMode || (targetElement ? APPLY_MODE.SUB_FIELD : APPLY_MODE.WHOLE_BLOCK);

    const detail = {
        fieldEdit,
        fieldType,
        fieldName: fieldName,
        // Explicit, machine-readable routing fields (no longer derived from
        // the display label on the backend):
        subFieldKey: extra.subFieldKey || '',
        metaKeys: extra.metaKeys || [],
        currentValue: getCurrentValue(fieldEdit, fieldType, currentInput),
        contentTypeName: getContentTypeName(doc),
        language: doc.querySelector(SELECTORS.languageMeta)?.content || 'eng-GB',
        contentTitle: getContentTitle(doc),
        siblingFields: getSiblingFields(doc, getFieldIdentifier(currentInput)),
        contentId: getContentId(doc),
        onApply: (suggestion, mode) => applyToField(fieldEdit, fieldType, currentInput, suggestion, mode, resolvedApplyMode),
    };

    doc.dispatchEvent(new CustomEvent('ai-suggest:open', { detail }));
}

/**
 * Find the input-actions wrapper for a field. In Ibexa, text inputs
 * live inside `.ibexa-input-text-wrapper__input-wrapper` with a
 * sibling `.ibexa-input-text-wrapper__actions` for action buttons.
 * Both are children of `.ibexa-input-text-wrapper`.
 *
 * DOM structure:
 *   .ibexa-input-text-wrapper
 *     ├── .ibexa-input-text-wrapper__input-wrapper
 *     │   └── input.ibexa-data-source__input
 *     └── .ibexa-input-text-wrapper__actions
 *         └── button (clear, etc.)
 *
 * Falls back to creating the actions div if the wrapper exists but
 * has no actions container yet. Returns null when no wrapper is found.
 */
function findInputActionsWrapper(doc, fieldEdit, targetElement) {
    const input = targetElement || fieldEdit.querySelector(SELECTORS.dataInput);
    if (!input) return null;

    const wrapper = input.closest('.ibexa-input-text-wrapper');
    if (!wrapper) return null;

    let actions = wrapper.querySelector('.ibexa-input-text-wrapper__actions');
    if (!actions) {
        actions = doc.createElement('div');
        actions.className = 'ibexa-input-text-wrapper__actions';
        wrapper.appendChild(actions);
    }
    return actions;
}

/**
 * Inject an AI button and a translate button into a single field-edit element (if eligible).
 */
function injectButton(doc, fieldEdit) {
    const fieldType = getFieldType(fieldEdit);
    if (!fieldType) return;

    if (fieldType === 'novaseometas') {
        // Whole-block button on the main label (only once)
        if (!fieldEdit.querySelector(SELECTORS.trigger)) {
            const label = fieldEdit.querySelector(SELECTORS.fieldLabel)
                || fieldEdit.querySelector('.ibexa-field-edit__label-wrapper label')
                || fieldEdit.querySelector('legend');
            if (label) {
                const btn = createAiButton(doc);
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openAiModal(
                        doc, fieldEdit, fieldType, null, 'SEO Metas', APPLY_MODE.WHOLE_BLOCK,
                        { metaKeys: collectNovaseoMetaKeys(fieldEdit) }
                    );
                });
                label.style.position = 'relative';
                label.appendChild(btn);
            }
        }
        // Per-sub-field buttons on every eligible meta row
        injectNovaseoMetaButtons(doc, fieldEdit, openAiModal);
        return;
    }

    if (fieldType === 'ezmatrix') {
        // Field-level button (per-row buttons are out of scope for v1).
        // Placed in the matrix's actions div (next to Add/Delete);
        // falls back to the field label.
        if (!fieldEdit.querySelector(SELECTORS.trigger)) {
            const target = fieldEdit.querySelector(MATRIX.actions)
                || fieldEdit.querySelector(SELECTORS.fieldLabel)
                || fieldEdit.querySelector('.ibexa-field-edit__label-wrapper label')
                || fieldEdit.querySelector('legend');
            if (target) {
                const btn = createAiButton(doc);
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openAiModal(
                        doc, fieldEdit, fieldType, null, getFieldLabel(fieldEdit), APPLY_MODE.WHOLE_BLOCK
                    );
                });
                target.style.position = 'relative';
                target.appendChild(btn);
            }
        }
        return;
    }

    // Don't inject twice
    if (fieldEdit.querySelector(SELECTORS.trigger)) return;

    const actionsWrapper = findInputActionsWrapper(doc, fieldEdit);
    if (!actionsWrapper) return;

    // AI suggest button
    const aiBtn = createAiButton(doc);
    aiBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openAiModal(doc, fieldEdit, fieldType);
    });
    actionsWrapper.appendChild(aiBtn);

    // Translate button
    const translateBtn = createTranslateButton(doc);
    translateBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openTranslateModal(doc, fieldEdit, fieldType);
    });
    actionsWrapper.appendChild(translateBtn);
}

/**
 * Open the modal with the translate action pre-selected (no source language
 * pre-filled — the user picks it from the dropdown).
 */
function openTranslateModal(doc, fieldEdit, fieldType) {
    const currentInput = fieldEdit.querySelector(SELECTORS.dataInput);
    const fieldName = getFieldLabel(fieldEdit);

    const detail = {
        fieldEdit,
        fieldType,
        fieldName,
        subFieldKey: '',
        metaKeys: [],
        currentValue: getCurrentValue(fieldEdit, fieldType, currentInput),
        contentTypeName: getContentTypeName(doc),
        language: doc.querySelector(SELECTORS.languageMeta)?.content || 'eng-GB',
        contentTitle: getContentTitle(doc),
        siblingFields: getSiblingFields(doc, getFieldIdentifier(currentInput)),
        contentId: getContentId(doc),
        onApply: (suggestion, mode) => applyToField(fieldEdit, fieldType, currentInput, suggestion, mode, APPLY_MODE.SUB_FIELD),
        hintAction: 'translate',
        hintSourceLanguage: '',
    };
    doc.dispatchEvent(new CustomEvent('ai-suggest:open', { detail }));
}

/**
 * Initial scan: find every .ibexa-field-edit in the DOM and inject buttons.
 */
export function scanFields(doc) {
    doc.querySelectorAll(SELECTORS.fieldEdit).forEach((el) => injectButton(doc, el));
    injectTranslateButtonsForSiblings(doc, (fieldEdit, fieldType, sourceLanguage) => {
        // Translate from a sibling: open the modal with the translate
        // action pre-selected and the source language pre-filled. The
        // modal handles the actual translation flow.
        const currentInput = fieldEdit.querySelector(SELECTORS.dataInput);
        const fieldName = getFieldLabel(fieldEdit);
        const detail = {
            fieldEdit,
            fieldType,
            fieldName,
            subFieldKey: '',
            metaKeys: [],
            currentValue: getCurrentValue(fieldEdit, fieldType, currentInput),
            contentTypeName: undefined,
            language: '',
            contentTitle: '',
            siblingFields: [],
            contentId: '',
            onApply: (suggestion, mode) => applyToField(fieldEdit, fieldType, currentInput, suggestion, mode, APPLY_MODE.SUB_FIELD),
            // New: hint to the modal to pre-select translate + language.
            hintAction: 'translate',
            hintSourceLanguage: sourceLanguage,
        };
        doc.dispatchEvent(new CustomEvent('ai-suggest:open', { detail }));
    });
}

/**
 * Watch for new .ibexa-field-edit nodes added to the DOM and inject buttons
 * into them as they appear. The scan is coalesced into a single rAF
 * callback so a burst of mutations only triggers one re-scan.
 */
export function observeFields(doc) {
    let scheduled = false;
    const scheduleScan = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            scanFields(doc);
        });
    };

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;
                if (
                    node.matches?.(SELECTORS.fieldEdit) ||
                    node.querySelector?.(SELECTORS.fieldEdit)
                ) {
                    scheduleScan();
                    return;
                }
            }
        }
    });

    observer.observe(doc.body, { childList: true, subtree: true });
}
