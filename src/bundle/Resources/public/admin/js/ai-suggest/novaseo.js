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
 * Create the ✨ AI button element used as a field-level trigger.
 * Centralised so styling/aria changes happen in one place.
 */
export function createAiButton(doc, extraClass = '') {
    const btn = doc.createElement('button');
    btn.type = 'button';
    btn.className = `ibexa-btn ibexa-btn--primary ibexa-btn--small ai-suggest-trigger ${extraClass}`.trim();
    btn.setAttribute('aria-label', 'AI content assistant');
    btn.title = 'Generate content with AI';
    btn.innerHTML = `
        <svg class="ibexa-icon ibexa-icon--tiny-small" viewBox="0 0 32 32">
            <path fill="currentColor" d="M25.833 7.333c-0.217-0.001-0.423-0.053-0.605-0.144l0.008 0.004c-0.441-0.224-0.738-0.674-0.738-1.193 0-0.218 0.052-0.423 0.145-0.605l-0.003 0.008 2-4c0.224-0.441 0.674-0.738 1.193-0.738 0.737 0 1.334 0.597 1.334 1.334 0 0.217-0.052 0.423-0.144 0.604l0.003-0.008-2 4c-0.224 0.44-0.674 0.737-1.192 0.737h0zM18.11 16.293l5.333-5.333c0.241-0.241 0.391-0.575 0.391-0.943 0-0.737-0.597-1.334-1.334-1.334-0.368 0-0.702 0.149-0.943 0.391l-5.333 5.333c-0.241 0.241-0.391 0.575-0.391 0.943 0 0.737 0.597 1.334 1.334 1.334 0.368 0 0.702-0.149 0.943-0.391zM2.777 31.627l12.667-12.667c0.241-0.241 0.391-0.575 0.391-0.943 0-0.737-0.597-1.334-1.334-1.334-0.368 0-0.702 0.149-0.943 0.391L0.89 29.74c-0.241 0.241-0.391 0.575-0.391 0.943 0 0.737 0.597 1.334 1.334 1.334 0.368 0 0.702-0.149 0.943-0.391z"/>
        </svg>
        <span class="ibexa-btn__label">AI</span>
    `;
    return btn;
}

/**
 * Inject per-sub-field AI buttons into every eligible meta row of a
 * novaseometas container. Each row is a `.ibexa-data-source__input-wrapper`
 * containing a hidden `name` input (the meta key) and a `content` input/textarea.
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

        const contentWrapper = row.querySelector(NOVASEO.contentWrapper);
        if (contentWrapper) {
            contentWrapper.style.position = 'relative';
            contentWrapper.appendChild(btn);
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
        btn.textContent = `🌐 Translate from ${detection.sourceLanguage}`;
        btn.setAttribute('aria-label', `Translate to current language from ${detection.sourceLanguage}`);
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
