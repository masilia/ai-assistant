/**
 * Shared JSDoc type definitions for the AI Assistant frontend.
 *
 * These declarations are consumed via '@type/import' references
 * in components and hooks so any IDE/TS-Check picks them up. They mirror
 * the JSON envelopes returned by the backend controllers exactly.
 *
 * @fileoverview
 * Central source of truth for frontend data shapes. If the backend
 * response shape changes, update here first.
 */

/**
 * @typedef {Object} Provider
 * @property {number}      id                Auto-increment primary key
 * @property {string}      name              Display name (e.g. "OpenAI Production")
 * @property {string}      identifier        Provider identifier: openai|anthropic|mistral|ollama|minimax|qwen
 * @property {string[]}    siteaccesses      Siteaccess names this provider is assigned to
 * @property {?string}     apiKey            '••••••••' if a key is set, null otherwise
 * @property {?string}     apiUrl            Custom endpoint URL or null
 * @property {?number}     activeChatModelId  FK → Model.id for the active chat model, or null
 * @property {?number}     activeImageModelId FK → Model.id for the active image model, or null
 */

/**
 * @typedef {Object} Model
 * @property {number}  id            Auto-increment primary key
 * @property {number}  providerId    FK → Provider.id
 * @property {string}  providerName  Display name of the parent provider
 * @property {string}  name          Display name (e.g. "GPT-4o Production")
 * @property {string}  identifier    API model identifier (e.g. "gpt-4o", "claude-3-5-sonnet-20241022")
 * @property {number}  temperature   0.0..2.0
 * @property {number}  maxTokens     1..
 * @property {boolean} supportsImage Whether this model supports image generation
 */

/**
 * @typedef {Object} TestResult
 * @property {boolean} success  Whether the connection test succeeded
 * @property {string}  message  Human-readable message (cleaned of provider JSON noise)
 */

/**
 * @typedef {Object} DashboardData
 * @property {Provider[]} providers         All configured providers
 * @property {Model[]}    models            All configured models
 * @property {string[]}   siteaccesses      All available siteaccess names (for the form dropdown)
 * @property {string}     currentSiteaccess Name of the siteaccess the admin is currently in
 */

/**
 * Field context payload dispatched by ai-suggest-button.js in the
 * ai-suggest:open custom event. Consumed by AiSuggestModal.
 *
 * @typedef {Object} FieldContext
 * @property {string}                      fieldType        Field-type identifier: ezstring|eztext|ezrichtext|novaseometas
 * @property {string}                      fieldName        Human-readable label (e.g. "Short description")
 * @property {string}                      [subFieldKey]    novaseometas meta key (e.g. "title"), empty otherwise
 * @property {string[]}                    [metaKeys]       For novaseometas whole-block: the editable AI-eligible metas
 * @property {string}                      currentValue     Current field value (CKEditor data for rich text)
 * @property {string}                      contentTypeName  e.g. "Article"
 * @property {string}                      language         Ibexa locale code, e.g. "eng-GB"
 * @property {string}                      [contentTitle]   Resolved content title
 * @property {Array<{label: string, value: string}>} [siblingFields] Other fields in the same content item
 * @property {string|number}               [contentId]      Content ID, or '' for unsaved drafts
 * @property {(suggestion: string, mode: 'replace'|'append') => {success: true}|{success: false, error: string}} onApply
 *                                                          Callback that writes the AI output to the field
 */

/**
 * Custom event detail shape for 'ai-suggest:open' (the backticks in the
 * prose below are intentional literals, not template syntax).
 *
 * @typedef {FieldContext} AiSuggestOpenDetail
 */

export {};
