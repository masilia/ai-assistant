/**
 * Centralized DOM contracts for the AI suggest injector. All CSS selectors
 * and name patterns live here so a DOM change in Ibexa can be adapted
 * without grepping through 500+ lines of injector code.
 */

export const SELECTORS = {
    fieldEdit:        '.ibexa-field-edit',
    fieldLabel:       '.ibexa-field-edit__label',
    dataInput:        '.ibexa-data-source__input',
    trigger:          '.ai-suggest-trigger',
    editHeaderAction: '.ibexa-edit-header__action-name',
    form:             'form.ibexa-form-validate',
    languageMeta:     'meta[name="LanguageCode"]',
    richTextContainer:'.ibexa-field-edit--ezrichtext',
};

// Ibexa input name pattern: ...[fieldsData][<identifier>][value]
export const FIELD_NAME_RE = /\[fieldsData\]\[([^\]]+)\]\[value\]/;

// Ibexa edit URL: /content/edit/{contentId}/{versionNo}/{language}
export const CONTENT_EDIT_RE = /\/content\/edit\/(\d+)\//;

export const TITLE_IDENTIFIERS = new Set(['title', 'name']);

// Meta keys where AI generation does not make sense (URLs, images, enums).
export const SKIP_META_KEYS = new Set(['og:image', 'twitter:image', 'canonical', 'type', 'robots']);

// novaseometas DOM contract. Each meta is a `row` containing a hidden
// `nameInput` (whose value is the meta key) and a `contentInput`
// (the editable value).
export const NOVASEO = {
    container:      '.ibexa-field-edit--novaseometas',
    row:            '.ibexa-data-source__input-wrapper',
    nameInput:      'input[type="hidden"][name$="[name]"]',
    contentInput:   '.ibexa-data-source__field--content input, .ibexa-data-source__field--content textarea',
    contentWrapper: '.ibexa-data-source__field--content',
};

// ezimage DOM contract. Two-state: image uploaded vs upload zone.
export const EZIMAGE = {
    field:          '.ibexa-field-edit--ezimage',
    preview:        '.ibexa-field-edit__preview',
    altTextWrapper: '.ibexa-field-edit-preview__image-alt',
    altTextInput:   '.ibexa-field-edit-preview__image-alt .ibexa-data-source__input',
    uploadZone:     '.ibexa-data-source',
};

// Matrix (ezmatrix) DOM contract. The edit view is a table with one
// <tr> per row; each <td> contains a plain <input>/<textarea> whose
// `name` ends in `[entries][<rowIndex>][<columnId>]`.
//
// Field-level AI button is injected into the matrix's
// `.ibexa-table-header__actions` div (next to Add/Delete).
// Per-row injection is not in scope for v1.
export const MATRIX = {
    field:        '.ibexa-field-edit--ezmatrix',
    rows:         'table tbody tr.ibexa-table__matrix-entry',
    columnHeader: 'th[data-identifier]',
    actions:      '.ibexa-table-header__actions',
    // Ibexa's "Add row" button. Clicking it triggers Ibexa's own row
    // template expansion (which auto-increments the [entries][N] index).
    addEntry:     '.ibexa-btn--add-matrix-entry',
    // Suffix of the Symfony form input name. The full name is e.g.
    //   ezplatform_content_forms_content_edit[fieldsData][matrix_id][entries][0][col_id]
    // We anchor to $ to be robust to whatever prefix Symfony adds.
    inputNameRe:  /\[entries\]\[(\d+)\]\[([^\]]+)\]$/,
};
