import { APPLY_MODE } from '../../components/ai-settings/constants.js';
import { SELECTORS, NOVASEO, SKIP_META_KEYS } from './selectors.js';
import { readNovaseoRow } from './apply.js';

/**
 * Collect the editable, AI-eligible meta keys present in a novaseometas
 * container (skipping SKIP_META_KEYS). This is the single source of truth
 * for which metas the whole-block generation should target, ensuring the
 * backend JSON schema matches the rows the editor actually sees.
 *
 * @returns {string[]}
 */
export function collectNovaseoMetaKeys(fieldEdit) {
    const keys = [];
    const seen = new Set();
    fieldEdit.querySelectorAll(NOVASEO.row).forEach((row) => {
        const { metaKey, contentInput } = readNovaseoRow(row);
        if (!metaKey || !contentInput) return;
        if (SKIP_META_KEYS.has(metaKey) || seen.has(metaKey)) return;
        seen.add(metaKey);
        keys.push(metaKey);
    });
    return keys;
}

/**
 * Create the AI button element used as a field-level trigger.
 * Ghost button with Sparkles icon — lives inside .ibexa-input-text-wrapper__actions.
 */
export function createAiButton(doc, extraClass = '') {
    const btn = doc.createElement('button');
    btn.type = 'button';
    btn.className = `btn ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-input-text-wrapper__action-btn ai-suggest-trigger ${extraClass}`.trim();
    btn.setAttribute('aria-label', 'AI content assistant');
    btn.title = 'Generate content with AI';
    btn.innerHTML = `
        <svg class="ibexa-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
        </svg>
    `;
    return btn;
}

/**
 * Inject per-sub-field AI buttons into every eligible meta row of a
 * novaseometas container. Each row is a `.ibexa-data-source__input-wrapper`
 * containing a hidden `name` input (the meta key) and a `content` input/textarea.
 * The button is placed inside `.ibexa-input-text-wrapper__actions` when available.
 */
export function injectNovaseoMetaButtons(doc, fieldEdit, onOpenModal) {
    fieldEdit.querySelectorAll(NOVASEO.row).forEach((row) => {
        if (row.querySelector(SELECTORS.trigger)) return;

        const { metaKey, contentInput } = readNovaseoRow(row);
        if (!metaKey || !contentInput) return;
        if (SKIP_META_KEYS.has(metaKey)) return;

        const btn = createAiButton(doc, 'ai-suggest-trigger--inline');
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            onOpenModal(fieldEdit, 'novaseometas', contentInput, `Meta: ${metaKey}`, APPLY_MODE.SUB_FIELD, { subFieldKey: metaKey });
        });

        // Try to find .ibexa-input-text-wrapper__actions inside the row
        const wrapper = row.querySelector('.ibexa-input-text-wrapper');
        if (wrapper) {
            let actions = wrapper.querySelector('.ibexa-input-text-wrapper__actions');
            if (!actions) {
                actions = doc.createElement('div');
                actions.className = 'ibexa-input-text-wrapper__actions';
                wrapper.appendChild(actions);
            }
            actions.appendChild(btn);
        } else {
            // Fallback: append to contentWrapper
            const contentWrapper = row.querySelector(NOVASEO.contentWrapper);
            if (contentWrapper) {
                contentWrapper.style.position = 'relative';
                contentWrapper.appendChild(btn);
            }
        }
    });
}

/**
 * Detect a likely-translated sibling field by its display label.
 *
 * Ibexa renders translated field labels as 'Title (French)',
 * 'Title (German)', 'Title (Allemand)', etc. The (Language) suffix
 * is a strong signal that the field is a translation of the current
 * one. The captured language name is forwarded to the modal which
 * uses it as the source language for the translate action.
 *
 * @param {string} label
 * @returns {{ isTranslation: true, sourceLanguage: string }|null}
 */
export function detectTranslationSibling(label) {
    if (!label) return null;
    const m = label.match(/\(([^)]+)\)\s*$/);
    if (!m) return null;
    const lang = m[1].trim();
    if (lang.length === 0 || lang.length > 40) return null;
    return { isTranslation: true, sourceLanguage: lang };
}

/**
 * Inject a one-click "Translate from {language}" button next to a
 * sibling field whose label matches the translation pattern.
 * Clicking the button dispatches a custom event the modal listens for.
 */
export function injectTranslateButtonsForSiblings(doc, onTrigger) {
    doc.querySelectorAll(SELECTORS.fieldEdit).forEach((fieldEdit) => {
        const fieldType = getFieldTypeFromClassList(fieldEdit);
        if (!fieldType) return;
        if (fieldType === 'novaseometas') return; // handled by per-row meta buttons

        const label = getFieldLabelForField(fieldEdit);
        const detection = detectTranslationSibling(label);
        if (!detection) return;

        // Don't double-inject.
        if (fieldEdit.querySelector('.ai-suggest-translate-sibling')) return;

        const btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-suggest-translate-sibling ibexa-btn ibexa-btn--tertiary ibexa-btn--small';
        btn.setAttribute('aria-label', `Translate to current language from ${detection.sourceLanguage}`);
        // Inline Lucide-style "Languages" icon + label. Built via
        // DOM API to avoid the JSX runtime in this vanilla-JS module.
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" aria-hidden="true" focusable="false"
                 style="vertical-align: -2px; margin-right: 4px;">
                <path d="m5 8 6 6" />
                <path d="m4 14 6-6 3-3" />
                <path d="M2 5h12" />
                <path d="M7 2h1" />
                <path d="m22 22-5-10-5 10" />
                <path d="M14 18h6" />
            </svg>
            Translate from ${detection.sourceLanguage}
        `;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            onTrigger(fieldEdit, fieldType, detection.sourceLanguage);
        });

        // Insert next to the field label.
        const labelEl = fieldEdit.querySelector(SELECTORS.fieldLabel);
        if (labelEl) {
            labelEl.appendChild(btn);
        }
    });
}

function getFieldTypeFromClassList(fieldEdit) {
    for (const [cls, type] of Object.entries(getSupportedFieldsForDetection())) {
        if (fieldEdit.classList.contains(cls)) return type;
    }
    return null;
}

function getFieldLabelForField(fieldEdit) {
    const label = fieldEdit.querySelector(SELECTORS.fieldLabel);
    return label ? label.textContent.trim().replace(/\s*\*$/, '') : '';
}

function getSupportedFieldsForDetection() {
    return window.AI_SUPPORTED_FIELDS || {};
}
