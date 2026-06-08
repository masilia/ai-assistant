/**
 * AI Suggest Button — Field-level AI assistant injector
 *
 * Scans the content edit form for supported field types and injects
 * a ✨ AI button next to each field label. Captures CKEditor instances
 * via the `ibexa-ckeditor:instance-ready` event for RichText injection.
 *
 * This file is the entry point only — all real logic lives in ./ai-suggest/.
 */
import { scanFields, observeFields } from './ai-suggest/fieldScanner.js';
import { attachCkEditorListener } from './ai-suggest/ckeditor.js';
import { fetchSupportedFields } from './ai-suggest/fieldTypes.js';

(function (doc) {
    'use strict';

    // Guard against double-initialization (e.g. script re-injected on navigation).
    if (doc.__aiSuggestInitialized) return;
    doc.__aiSuggestInitialized = true;

    attachCkEditorListener(doc);

    const init = () => {
        scanFields(doc);
        fetchSupportedFields(scanFields.bind(null, doc));
        observeFields(doc);
    };

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(document);
