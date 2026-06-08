/**
 * AI Suggest Button — Field-level AI assistant injector
 *
 * Scans the content edit form for supported field types and injects
 * a ✨ AI button next to each field label. Captures CKEditor instances
 * via the `ibexa-ckeditor:instance-ready` event for RichText injection.
 */
import { AI_ROUTES } from '../components/ai-settings/api-routes.js';
import { APPLY_MODE, SUGGEST_MODE } from '../components/ai-settings/constants.js';

(function (doc) {
    'use strict';

    // Guard against double-initialization (e.g. script re-injected on navigation).
    if (doc.__aiSuggestInitialized) return;
    doc.__aiSuggestInitialized = true;

    // Immediate fallback used before (or if) the authoritative list is fetched
    // from the backend. The backend FieldFormatResolver is the single source of
    // truth; this only mirrors it so buttons can render without waiting on a
    // network round-trip. Kept in sync via fetchSupportedFields() below.
    const DEFAULT_SUPPORTED_FIELDS = {
        'ibexa-field-edit--ezstring': 'ezstring',
        'ibexa-field-edit--eztext': 'eztext',
        'ibexa-field-edit--ezrichtext': 'ezrichtext',
        'ibexa-field-edit--novaseometas': 'novaseometas',
    };

    // Read lazily so a list fetched/assigned after initial load is picked up by
    // subsequent scans.
    const getSupportedFields = () => window.AI_SUPPORTED_FIELDS || DEFAULT_SUPPORTED_FIELDS;

    // Centralized selectors / patterns so DOM contracts live in one place.
    const SELECTORS = {
        fieldEdit: '.ibexa-field-edit',
        fieldLabel: '.ibexa-field-edit__label',
        dataInput: '.ibexa-data-source__input',
        trigger: '.ai-suggest-trigger',
        editHeaderAction: '.ibexa-edit-header__action-name',
        form: 'form.ibexa-form-validate',
        languageMeta: 'meta[name="LanguageCode"]',
    };
    // Ibexa input name pattern: ...[fieldsData][<identifier>][value]
    const FIELD_NAME_RE = /\[fieldsData\]\[([^\]]+)\]\[value\]/;
    // Ibexa edit URL: /content/edit/{contentId}/{versionNo}/{language}
    const CONTENT_EDIT_RE = /\/content\/edit\/(\d+)\//;
    const TITLE_IDENTIFIERS = new Set(['title', 'name']);

    // Store CKEditor instances keyed by their container element
    const editorInstances = new WeakMap();

    // Meta keys where AI generation does not make sense (URLs, images, enums).
    const SKIP_META_KEYS = new Set(['og:image', 'twitter:image', 'canonical', 'type', 'robots']);

    // Single source of truth for the novaseometas DOM contract. Each meta is a
    // `row` containing a hidden `nameInput` (whose value is the meta key) and a
    // `contentInput` (the editable value).
    const NOVASEO = {
        container: '.ibexa-field-edit--novaseometas',
        row: '.ibexa-data-source__input-wrapper',
        nameInput: 'input[type="hidden"][name$="[name]"]',
        contentInput: '.ibexa-data-source__field--content input, .ibexa-data-source__field--content textarea',
        contentWrapper: '.ibexa-data-source__field--content',
    };

    /**
     * Listen for CKEditor instance-ready events to capture editor references.
     */
    doc.addEventListener('ibexa-ckeditor:instance-ready', (e) => {
        const container = e.target;
        const fieldEdit = container.closest('.ibexa-field-edit--ezrichtext');
        if (fieldEdit && e.detail?.editor) {
            editorInstances.set(fieldEdit, e.detail.editor);
        }
    }, true); // useCapture: CKEditor dispatches on the container, we listen on document

    /**
     * Detect the field type from a .ibexa-field-edit element.
     */
    function getFieldType(fieldEdit) {
        for (const [cls, type] of Object.entries(getSupportedFields())) {
            if (fieldEdit.classList.contains(cls)) return type;
        }
        return null;
    }

    /**
     * Get the field label text.
     */
    function getFieldLabel(fieldEdit) {
        const label = fieldEdit.querySelector('.ibexa-field-edit__label');
        return label ? label.textContent.trim().replace(/\s*\*$/, '') : '';
    }

    /**
     * Get the current value of a field.
     */
    function getCurrentValue(fieldEdit, fieldType, targetElement) {
        if (fieldType === 'ezrichtext') {
            const editor = editorInstances.get(fieldEdit);
            return editor ? editor.getData() : '';
        }
        const input = targetElement || fieldEdit.querySelector('.ibexa-data-source__input');
        return input ? input.value : '';
    }

    /**
     * Strip surrounding markdown code fences (```...``` or ```json...```) from
     * an AI response. Single source of truth used by all sanitizers.
     */
    function stripCodeFences(text) {
        if (typeof text !== 'string') return '';
        let t = text.trim();
        if (t.startsWith('```')) {
            t = t.replace(/^```(json)?/i, '').replace(/```$/, '').trim();
        }
        return t;
    }

    /**
     * Read a novaseometas row into its meta key and editable input.
     * @returns {{ metaKey: string, contentInput: HTMLElement|null }}
     */
    function readNovaseoRow(row) {
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
    function sanitizeAiText(text) {
        let t = stripCodeFences(text);
        t = t.replace(/^["'`]+|["'`]+$/g, '').trim();
        t = t.replace(/^(value|content|answer)\s*[:=]\s*/i, '').trim();
        return t;
    }

    /**
     * Try to extract a single meta value from an AI response. Handles:
     * - Plain text → returned as-is after sanitization
     * - JSON object → extract the value for `metaKey` only; returns null if the
     *   key is absent so the caller can surface an actionable error instead of
     *   silently writing a wrong value.
     * Returns the extracted string, or null if no usable value.
     */
    function extractSubFieldValue(suggestion, metaKey) {
        const text = stripCodeFences(suggestion);

        // Attempt JSON parse
        try {
            const data = JSON.parse(text);
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                if (metaKey && data[metaKey] !== undefined && data[metaKey] !== null) {
                    return sanitizeAiText(String(data[metaKey]));
                }
                // Key not found in JSON — return null so the caller can error
                return null;
            }
        } catch (e) {
            // Not JSON, treat as plain text
        }

        return sanitizeAiText(text);
    }

    /**
     * Apply AI-generated content to a field.
     * Returns { success: true } on success, or { success: false, error: string } on failure.
     */
    function applyToField(fieldEdit, fieldType, targetElement, suggestion, mode, applyMode) {
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
                    input.value = (input.value ? input.value + ' ' : '') + text;
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
                targetElement.value = (targetElement.value ? targetElement.value + ' ' : '') + extracted;
            }
            targetElement.dispatchEvent(new Event('input', { bubbles: true }));
            return { success: true };
        }

        if (fieldType === 'ezrichtext') {
            const editor = editorInstances.get(fieldEdit);
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
     * Create the ✨ AI button element.
     */
    function createAiButton() {
        const btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'ibexa-btn ibexa-btn--primary ibexa-btn--small ai-suggest-trigger';
        btn.setAttribute('aria-label', 'AI content assistant');
        btn.title = 'Generate content with AI';
        btn.innerHTML = `
            <svg class="ibexa-icon ibexa-icon--tiny-small" viewBox="0 0 24 24">
                <path fill="currentColor" d="M9.937 3.314l1.563 3.186 3.186 1.563-3.186 1.563-1.563 3.186-1.563-3.186-3.186-1.563 3.186-1.563zM19 7l1 2 2 1-2 1-1 2-1-2-2-1 2-1zM14 17l1.25 2.75L18 21l-2.75 1.25L14 25l-1.25-2.75L10 21l2.75-1.25z"/>
            </svg>
            <span class="ibexa-btn__label">AI</span>
        `;
        return btn;
    }

    /**
     * Resolve the content type name from the page header or a data attribute.
     * The Ibexa edit header renders "Editing ContentTypeName".
     */
    function getContentTypeName() {
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
    function getContentTitle() {
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
    function getFieldIdentifier(input) {
        return input?.name?.match(FIELD_NAME_RE)?.[1] || null;
    }

    /**
     * Collect sibling field values (one per identifier) to give the AI context.
     * Excludes the field being edited and the title/name fields.
     */
    function getSiblingFields(currentIdentifier) {
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

        // Include novaseometas meta values as sibling context. The novaseometas
        // container's collection rows don't match FIELD_NAME_RE, so they are
        // skipped by the loop above — add them here so the AI can see what other
        // SEO fields are already filled in when generating a single meta.
        const novaseoContainer = doc.querySelector(NOVASEO.container);
        if (novaseoContainer) {
            novaseoContainer.querySelectorAll(NOVASEO.row).forEach((row) => {
                const { metaKey, contentInput } = readNovaseoRow(row);
                if (!metaKey || !contentInput) return;
                const value = contentInput.value?.trim();
                if (!value) return;

                const dedupeKey = `meta:${metaKey}`;
                if (seenIdentifiers.has(dedupeKey)) return;

                seenIdentifiers.add(dedupeKey);
                siblingFields.push({ label: `SEO ${metaKey}`, value });
            });
        }

        return siblingFields;
    }

    /**
     * Resolve the content ID from the form action URL, location, or query string.
     * Returns '' for new (unsaved) content.
     */
    function getContentId() {
        const formAction = doc.querySelector(SELECTORS.form)?.action || '';
        return doc.querySelector('[data-content-id]')?.dataset.contentId
            || formAction.match(CONTENT_EDIT_RE)?.[1]
            || location.pathname.match(CONTENT_EDIT_RE)?.[1]
            || location.pathname.match(/\/(\d+)\//)?.[1]
            || new URL(location.href).searchParams.get('contentId')
            || '';
    }

    /**
     * Open the AI suggestion modal for a specific field.
     * @param {HTMLElement} fieldEdit       The .ibexa-field-edit container.
     * @param {string}      fieldType       Field type identifier.
     * @param {HTMLElement|null} targetElement The specific input to target (null for whole-block).
     * @param {string}      [subFieldName]  Display name for the field/sub-field.
     * @param {string}      [applyMode]     APPLY_MODE.WHOLE_BLOCK (parse JSON, distribute) or
     *                                      APPLY_MODE.SUB_FIELD (extract one key, sanitize).
     */
    function openAiModal(fieldEdit, fieldType, targetElement, subFieldName, applyMode, extra = {}) {
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
            contentTypeName: getContentTypeName(),
            language: doc.querySelector(SELECTORS.languageMeta)?.content || 'eng-GB',
            contentTitle: getContentTitle(),
            siblingFields: getSiblingFields(getFieldIdentifier(currentInput)),
            contentId: getContentId(),
            onApply: (suggestion, mode) => applyToField(fieldEdit, fieldType, currentInput, suggestion, mode, resolvedApplyMode),
        };

        doc.dispatchEvent(new CustomEvent('ai-suggest:open', { detail }));
    }

    /**
     * Collect the editable, AI-eligible meta keys present in a novaseometas
     * container (skipping SKIP_META_KEYS). This is the single source of truth
     * for which metas the whole-block generation should target, ensuring the
     * backend JSON schema matches the rows the editor actually sees.
     */
    function collectNovaseoMetaKeys(fieldEdit) {
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
     * Inject per-sub-field AI buttons into every eligible meta row of a
     * novaseometas container. Each row is a `.ibexa-data-source__input-wrapper`
     * containing a hidden `name` input (the meta key) and a `content` input/textarea.
     */
    function injectNovaseoMetaButtons(fieldEdit) {
        fieldEdit.querySelectorAll(NOVASEO.row).forEach((row) => {
            if (row.querySelector(SELECTORS.trigger)) return;

            const { metaKey, contentInput } = readNovaseoRow(row);
            if (!metaKey || !contentInput) return;
            if (SKIP_META_KEYS.has(metaKey)) return;

            const btn = createAiButton();
            btn.classList.add('ai-suggest-trigger--inline');
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openAiModal(fieldEdit, 'novaseometas', contentInput, `Meta: ${metaKey}`, APPLY_MODE.SUB_FIELD, { subFieldKey: metaKey });
            });

            const contentWrapper = row.querySelector(NOVASEO.contentWrapper);
            if (contentWrapper) {
                contentWrapper.style.position = 'relative';
                contentWrapper.appendChild(btn);
            }
        });
    }

    /**
     * Inject an AI button into a single field-edit element (if eligible).
     */
    function injectButton(fieldEdit) {
        const fieldType = getFieldType(fieldEdit);
        if (!fieldType) return;

        if (fieldType === 'novaseometas') {
            // Whole-block button on the main label (only once)
            if (!fieldEdit.querySelector(SELECTORS.trigger)) {
                const label = fieldEdit.querySelector(SELECTORS.fieldLabel)
                    || fieldEdit.querySelector('.ibexa-field-edit__label-wrapper label')
                    || fieldEdit.querySelector('legend');
                if (label) {
                    const btn = createAiButton();
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        openAiModal(fieldEdit, fieldType, null, 'SEO Metas', APPLY_MODE.WHOLE_BLOCK, {
                            metaKeys: collectNovaseoMetaKeys(fieldEdit),
                        });
                    });
                    label.style.position = 'relative';
                    label.appendChild(btn);
                }
            }
            // Per-sub-field buttons on every eligible meta row
            injectNovaseoMetaButtons(fieldEdit);
            return;
        }

        // Don't inject twice
        if (fieldEdit.querySelector(SELECTORS.trigger)) return;

        const label = fieldEdit.querySelector(SELECTORS.fieldLabel)
            || fieldEdit.querySelector('.ibexa-field-edit__label-wrapper label')
            || fieldEdit.querySelector('legend');
        if (!label) return;

        const btn = createAiButton();
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openAiModal(fieldEdit, fieldType);
        });

        label.style.position = 'relative';
        label.appendChild(btn);
    }

    /**
     * Initialize: scan fields and inject AI buttons.
     */
    function init() {
        doc.querySelectorAll(SELECTORS.fieldEdit).forEach(injectButton);
    }

    /**
     * Fetch the authoritative supported-field map from the backend
     * (FieldFormatResolver, the single source of truth) and re-scan so any
     * field types not covered by DEFAULT_SUPPORTED_FIELDS get buttons too.
     * Silently keeps the local fallback on any error.
     */
    function fetchSupportedFields() {
        fetch(AI_ROUTES.fieldTypes, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                const map = data && data.fieldTypes;
                if (map && typeof map === 'object' && Object.keys(map).length > 0) {
                    window.AI_SUPPORTED_FIELDS = map;
                    init();
                }
            })
            .catch(() => { /* keep DEFAULT_SUPPORTED_FIELDS */ });
    }

    /**
     * Watch for new .ibexa-field-edit nodes added to the DOM and inject buttons
     * into them as they appear (handles dynamically rendered fields).
     *
     * The scan is coalesced into a single rAF callback so a burst of mutations
     * (common on Ibexa admin pages) only triggers one re-scan.
     */
    function observeFields() {
        let scheduled = false;
        const scheduleScan = () => {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                init();
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

    // Run once after DOM is ready, then keep watching for dynamic fields.
    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', () => { init(); fetchSupportedFields(); observeFields(); });
    } else {
        init();
        fetchSupportedFields();
        observeFields();
    }
})(document);
