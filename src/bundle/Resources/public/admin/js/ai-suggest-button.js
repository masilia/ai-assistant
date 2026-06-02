/**
 * AI Suggest Button — Field-level AI assistant injector
 *
 * Scans the content edit form for supported field types and injects
 * a ✨ AI button next to each field label. Captures CKEditor instances
 * via the `ibexa-ckeditor:instance-ready` event for RichText injection.
 */
(function (doc) {
    'use strict';

    // Guard against double-initialization (e.g. script re-injected on navigation).
    if (doc.__aiSuggestInitialized) return;
    doc.__aiSuggestInitialized = true;

    const DEFAULT_SUPPORTED_FIELDS = {
        'ibexa-field-edit--ezstring': 'ezstring',
        'ibexa-field-edit--eztext': 'eztext',
        'ibexa-field-edit--ezrichtext': 'ezrichtext',
    };

    const SUPPORTED_FIELDS = window.AI_SUPPORTED_FIELDS || DEFAULT_SUPPORTED_FIELDS;

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
        for (const [cls, type] of Object.entries(SUPPORTED_FIELDS)) {
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
    function getCurrentValue(fieldEdit, fieldType) {
        if (fieldType === 'ezrichtext') {
            const editor = editorInstances.get(fieldEdit);
            return editor ? editor.getData() : '';
        }
        const input = fieldEdit.querySelector('.ibexa-data-source__input');
        return input ? input.value : '';
    }

    /**
     * Apply AI-generated content to a field.
     */
    function applyToField(fieldEdit, fieldType, suggestion, mode) {
        if (fieldType === 'ezrichtext') {
            const editor = editorInstances.get(fieldEdit);
            if (!editor) {
                console.warn('[AI] No CKEditor instance found for field');
                return;
            }
            if (mode === 'replace') {
                editor.setData(suggestion);
            } else {
                const current = editor.getData();
                editor.setData(current + suggestion);
            }
        } else {
            const input = fieldEdit.querySelector('.ibexa-data-source__input');
            if (!input) return;
            if (mode === 'replace') {
                input.value = suggestion;
            } else {
                input.value += suggestion;
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Create the ✨ AI button element.
     */
    function createAiButton() {
        const btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-suggest-trigger';
        btn.setAttribute('aria-label', 'AI content assistant');
        btn.title = 'Generate content with AI';
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z"/>
            </svg>
            <span>AI</span>
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
     */
    function openAiModal(fieldEdit, fieldType) {
        const currentInput = fieldEdit.querySelector(SELECTORS.dataInput);

        const detail = {
            fieldEdit,
            fieldType,
            fieldName: getFieldLabel(fieldEdit),
            currentValue: getCurrentValue(fieldEdit, fieldType),
            contentTypeName: getContentTypeName(),
            language: doc.querySelector(SELECTORS.languageMeta)?.content || 'eng-GB',
            contentTitle: getContentTitle(),
            siblingFields: getSiblingFields(getFieldIdentifier(currentInput)),
            contentId: getContentId(),
            onApply: (suggestion, mode) => applyToField(fieldEdit, fieldType, suggestion, mode),
        };

        doc.dispatchEvent(new CustomEvent('ai-suggest:open', { detail }));
    }



    /**
     * Inject an AI button into a single field-edit element (if eligible).
     */
    function injectButton(fieldEdit) {
        const fieldType = getFieldType(fieldEdit);
        if (!fieldType) return;

        // Don't inject twice
        if (fieldEdit.querySelector(SELECTORS.trigger)) return;

        const label = fieldEdit.querySelector(SELECTORS.fieldLabel);
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
        doc.addEventListener('DOMContentLoaded', () => { init(); observeFields(); });
    } else {
        init();
        observeFields();
    }
})(document);
