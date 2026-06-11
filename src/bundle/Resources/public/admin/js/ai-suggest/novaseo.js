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
             fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/>
        </svg>
    `;
    return btn;
}

/**
 * Create a translate button element. Ghost button with Languages icon.
 * Lives inside .ibexa-input-text-wrapper__actions next to the AI button.
 */
export function createTranslateButton(doc) {
    const btn = doc.createElement('button');
    btn.type = 'button';
    btn.className = 'btn ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-input-text-wrapper__action-btn ai-suggest-translate-trigger';
    btn.setAttribute('aria-label', 'Translate field');
    btn.title = 'Translate with AI';
    btn.innerHTML = `
        <svg class="ibexa-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="m5 8 6 6" />
            <path d="m4 14 6-6 3-3" />
            <path d="M2 5h12" />
            <path d="M7 2h1" />
            <path d="m22 22-5-10-5 10" />
            <path d="M14 18h6" />
        </svg>
    `;
    return btn;
}

/**
 * Create an image generation button for ezimage fields.
 * Placed inside .ibexa-field-edit-preview__actions (next to Delete/Preview).
 */
export function createImageGenButton(doc) {
    const btn = doc.createElement('button');
    btn.type = 'button';
    btn.className = 'btn ibexa-btn ibexa-btn--ghost ibexa-btn--small ai-suggest-image-gen-trigger';
    btn.setAttribute('aria-label', 'Generate image with AI');
    btn.title = 'Generate image with AI';
    btn.innerHTML = `
        <svg class="ibexa-icon ibexa-icon--small" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
             fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
        </svg>
        <span class="ibexa-btn__label">Generate</span>
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

        const btn = createAiButton(doc, '');
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
 * Inject a one-click "Translate from {language}" button into the
 * input-actions wrapper of a sibling field whose label matches the
 * translation pattern. Clicking the button dispatches a custom event
 * the modal listens for.
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

        // Find the input-actions wrapper (same as the AI suggest button).
        const input = fieldEdit.querySelector(SELECTORS.dataInput);
        if (!input) return;

        const wrapper = input.closest('.ibexa-input-text-wrapper');
        if (!wrapper) return;

        let actions = wrapper.querySelector('.ibexa-input-text-wrapper__actions');
        if (!actions) {
            actions = doc.createElement('div');
            actions.className = 'ibexa-input-text-wrapper__actions';
            wrapper.appendChild(actions);
        }

        const btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'btn ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-input-text-wrapper__action-btn ai-suggest-translate-sibling';
        btn.setAttribute('aria-label', `Translate to current language from ${detection.sourceLanguage}`);
        // Inline Lucide-style "Languages" icon — matches icons.jsx#L136.
        btn.innerHTML = `
            <svg class="ibexa-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="m5 8 6 6" />
                <path d="m4 14 6-6 3-3" />
                <path d="M2 5h12" />
                <path d="M7 2h1" />
                <path d="m22 22-5-10-5 10" />
                <path d="M14 18h6" />
            </svg>
        `;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            onTrigger(fieldEdit, fieldType, detection.sourceLanguage);
        });

        actions.appendChild(btn);
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
