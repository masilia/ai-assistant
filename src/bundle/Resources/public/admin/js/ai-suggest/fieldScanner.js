import { SELECTORS } from './selectors.js';
import { getFieldType, getFieldLabel, getCurrentValue, getContentTypeName, getContentTitle, getSiblingFields, getFieldIdentifier, getContentId } from './fieldInfo.js';
import { collectNovaseoMetaKeys, injectNovaseoMetaButtons, createAiButton } from './novaseo.js';
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
 * Inject an AI button into a single field-edit element (if eligible).
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

    // Don't inject twice
    if (fieldEdit.querySelector(SELECTORS.trigger)) return;

    const label = fieldEdit.querySelector(SELECTORS.fieldLabel)
        || fieldEdit.querySelector('.ibexa-field-edit__label-wrapper label')
        || fieldEdit.querySelector('legend');
    if (!label) return;

    const btn = createAiButton(doc);
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openAiModal(doc, fieldEdit, fieldType);
    });

    label.style.position = 'relative';
    label.appendChild(btn);
}

/**
 * Initial scan: find every .ibexa-field-edit in the DOM and inject buttons.
 */
export function scanFields(doc) {
    doc.querySelectorAll(SELECTORS.fieldEdit).forEach((el) => injectButton(doc, el));
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
