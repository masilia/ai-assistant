import React, { useState, useEffect, useRef, useCallback } from 'react';
import ReactDOM from 'react-dom';
import { AI_ROUTES } from './ai-settings/api-routes.js';
import { QUICK_ACTIONS, SUGGEST_MODE, applyQuickAction } from './ai-settings/constants.js';

/**
 * AI Suggest Modal — React component for the AI content assistant prompt/preview UI.
 *
 * Listens for 'ai-suggest:open' custom events dispatched by ai-suggest-button.js
 * and renders a floating modal with prompt input, preview, and apply controls.
 */
function AiSuggestModal() {
    const [open, setOpen] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [suggestion, setSuggestion] = useState('');
    const [loading, setLoading] = useState(false);
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState('');
    const [mode, setMode] = useState(SUGGEST_MODE.REPLACE);
    const [fieldContext, setFieldContext] = useState(null);
    const [selectedQuickAction, setSelectedQuickAction] = useState(null);
    const [sourceLanguage, setSourceLanguage] = useState('');
    const [showSourceLangInput, setShowSourceLangInput] = useState(false);

    const promptRef = useRef(null);
    const onApplyRef = useRef(null);
    const abortControllerRef = useRef(null);

    // Listen for the custom event from ai-suggest-button.js
    useEffect(() => {
        const handler = (e) => {
            const detail = e.detail;
            setFieldContext({
                fieldType: detail.fieldType,
                fieldName: detail.fieldName,
                subFieldKey: detail.subFieldKey || '',
                metaKeys: detail.metaKeys || [],
                currentValue: detail.currentValue,
                contentTypeName: detail.contentTypeName,
                language: detail.language,
                contentTitle: detail.contentTitle || '',
                siblingFields: detail.siblingFields || [],
                contentId: detail.contentId || '',
            });
            onApplyRef.current = detail.onApply;
            setOpen(true);
            setPrompt('');
            setSuggestion('');
            setError('');
            setLoading(false);
            setStreaming(false);
            setMode(SUGGEST_MODE.REPLACE);
            setSelectedQuickAction(null);
            setSourceLanguage('');
            setShowSourceLangInput(false);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };

        document.addEventListener('ai-suggest:open', handler);
        return () => document.removeEventListener('ai-suggest:open', handler);
    }, []);

    // Auto-focus prompt input when modal opens
    useEffect(() => {
        if (open && promptRef.current) {
            setTimeout(() => promptRef.current?.focus(), 100);
        }
    }, [open]);

    // Close on Escape
    useEffect(() => {
        if (!open) return;
        const handler = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [open]);

    const handleGenerate = useCallback(async () => {
        if (!prompt.trim() || !fieldContext) return;

        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }
        abortControllerRef.current = new AbortController();

        setLoading(true);
        setStreaming(true);
        setError('');
        setSuggestion('');

        try {
            const res = await fetch(AI_ROUTES.suggestStream, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    fieldType: fieldContext.fieldType,
                    prompt: prompt.trim(),
                    currentValue: fieldContext.currentValue,
                    contentType: fieldContext.contentTypeName,
                    fieldName: fieldContext.fieldName,
                    language: fieldContext.language,
                    contentTitle: fieldContext.contentTitle,
                    siblingFields: fieldContext.siblingFields,
                    contentId: fieldContext.contentId,
                    sourceLanguage: sourceLanguage,
                    subFieldKey: fieldContext.subFieldKey,
                    metaKeys: fieldContext.metaKeys,
                }),
                signal: abortControllerRef.current.signal,
            });

            if (!res.ok) {
                const data = await res.json();
                const errorMessage = data.error?.message
                    || (typeof data.error === 'string' ? data.error : 'An error occurred');
                setError(errorMessage);
                setStreaming(false);
                setLoading(false);
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            let streamDone = false;

            while (!streamDone) {
                const { done, value } = await reader.read();

                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed.startsWith('data: ')) continue;

                    const jsonStr = trimmed.slice(6);
                    if (!jsonStr) continue;

                    try {
                        const data = JSON.parse(jsonStr);

                        if (data.error) {
                            const errorMessage = data.error.message || 'An error occurred';
                            setError(errorMessage);
                            setStreaming(false);
                            streamDone = true;
                            break;
                        }

                        if (data.token) {
                            setSuggestion(prev => prev + data.token);
                        }

                        if (data.done) {
                            setStreaming(false);
                            streamDone = true;
                            break;
                        }
                    } catch (e) {
                        // Skip malformed JSON lines
                    }
                }
            }
        } catch (err) {
            if (err.name === 'AbortError') {
                // Stream was cancelled, ignore
            } else {
                setError(err.message || 'Network error');
                setStreaming(false);
            }
        } finally {
            setLoading(false);
            setStreaming(false);
        }
    }, [fieldContext, prompt, sourceLanguage]);

    const handleApply = useCallback(() => {
        if (!suggestion || !onApplyRef.current) return;
        const result = onApplyRef.current(suggestion, mode);
        if (result && result.success === false) {
            setError(result.error || 'Failed to apply the suggestion.');
            return;
        }
        setOpen(false);
    }, [suggestion, mode]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            if (suggestion) {
                handleApply();
            } else {
                handleGenerate();
            }
        }
    }, [suggestion, handleApply, handleGenerate]);

    const handleQuickAction = useCallback((quickAction) => {
        setSelectedQuickAction(quickAction.id);

        if (quickAction.isTranslation) {
            setShowSourceLangInput(true);
            return;
        }

        const actionPrompt = applyQuickAction(quickAction.id, fieldContext);
        setPrompt(actionPrompt);
    }, [fieldContext]);

    const handleSourceLanguageSubmit = useCallback((lang) => {
        if (!lang || !fieldContext) return;

        setSourceLanguage(lang);
        setShowSourceLangInput(false);

        const actionPrompt = `Translate from ${lang} to ${fieldContext.language}`;
        setPrompt(actionPrompt);
    }, [fieldContext]);

    const handlePromptChange = useCallback((e) => {
        setPrompt(e.target.value);
        setSelectedQuickAction(null);
    }, []);

    const fieldTypeLabel = {
        ezstring: 'Text Line',
        eztext: 'Text Block',
        ezrichtext: 'Rich Text',
        novaseometas: 'SEO Metas',
    };

    const isNovaSeo = fieldContext?.fieldType === 'novaseometas';

    if (!open) return null;

    return (
        <>
            {/* Overlay */}
            <div className="ai-suggest-overlay" onClick={() => setOpen(false)} />

            {/* Modal — uses Ibexa modal structure */}
            <div className="ibexa-modal ai-suggest-modal" onKeyDown={handleKeyDown}>
                <div className="modal-dialog">
                    <div className="modal-content">

                        {/* Header */}
                        <div className="modal-header">
                            <h5 className="modal-title ai-suggest-modal__title">
                                <svg className="ibexa-icon ibexa-icon--small-medium ai-suggest-modal__title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
                                </svg>
                                AI Content Assistant
                            </h5>
                            <button
                                className="close ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-btn--small"
                                onClick={() => setOpen(false)}
                                type="button"
                                aria-label="Close"
                            >
                                <svg className="ibexa-icon ibexa-icon--small" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>

                        {/* Field info bar */}
                        <div className="ai-suggest-modal__field-info">
                            <span className="ai-suggest-modal__field-name">{fieldContext?.fieldName || 'Field'}</span>
                            <span className="ai-suggest-modal__field-type">
                                {fieldTypeLabel[fieldContext?.fieldType] || fieldContext?.fieldType}
                            </span>
                        </div>

                        {/* Body */}
                        <div className="modal-body">
                            {/* Prompt input */}
                            <div className="ai-suggest-modal__section">
                                <label className="ibexa-label" htmlFor="ai-prompt-input">
                                    What would you like to write?
                                </label>
                                <textarea
                                    ref={promptRef}
                                    id="ai-prompt-input"
                                    className="ibexa-input ibexa-input--textarea form-control"
                                    value={prompt}
                                    onChange={handlePromptChange}
                                    placeholder="e.g. Write a 2-paragraph introduction about renewable energy in the Mediterranean..."
                                    rows={3}
                                    disabled={loading}
                                />
                            </div>

                            {/* Quick actions */}
                            <div className="ai-suggest-modal__quick-actions">
                                <span className="ai-suggest-modal__quick-actions-label">Quick:</span>
                                <div className="ai-suggest-modal__quick-actions-list">
                                    {QUICK_ACTIONS.map((action) => (
                                        <button
                                            key={action.id}
                                            type="button"
                                            className={`ai-suggest-modal__quick-action ${selectedQuickAction === action.id ? 'ai-suggest-modal__quick-action--active' : ''}`}
                                            onClick={() => handleQuickAction(action)}
                                            disabled={loading || (isNovaSeo && action.isTranslation)}
                                            title={isNovaSeo && action.isTranslation ? 'Translation is not supported for SEO Metas' : action.promptTemplate}
                                        >
                                            <span className="ai-suggest-modal__quick-action-icon">{action.icon}</span>
                                            <span className="ai-suggest-modal__quick-action-label">{action.label}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Source language input for translation */}
                            {showSourceLangInput && (
                                <div className="ai-suggest-modal__source-lang">
                                    <label className="ibexa-label">
                                        Source language code (e.g., eng-GB, fre-FR):
                                    </label>
                                    <div className="ai-suggest-modal__source-lang-row">
                                        <input
                                            type="text"
                                            className="ibexa-input ibexa-input--text form-control"
                                            value={sourceLanguage}
                                            onChange={(e) => setSourceLanguage(e.target.value)}
                                            placeholder="eng-GB"
                                            disabled={loading}
                                        />
                                        <button
                                            type="button"
                                            className="ibexa-btn ibexa-btn--primary ibexa-btn--small"
                                            onClick={() => handleSourceLanguageSubmit(sourceLanguage)}
                                            disabled={!sourceLanguage.trim() || loading}
                                        >
                                            Use as Source
                                        </button>
                                        <button
                                            type="button"
                                            className="ibexa-btn ibexa-btn--ghost ibexa-btn--small"
                                            onClick={() => {
                                                setShowSourceLangInput(false);
                                                setSelectedQuickAction(null);
                                            }}
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Mode selector */}
                            <div className="ai-suggest-modal__mode">
                                <label className="form-check-inline ai-suggest-modal__mode-option">
                                    <input
                                        className="ibexa-input ibexa-input--radio"
                                        type="radio"
                                        name="ai-mode"
                                        value={SUGGEST_MODE.REPLACE}
                                        checked={mode === SUGGEST_MODE.REPLACE}
                                        onChange={() => setMode(SUGGEST_MODE.REPLACE)}
                                    />
                                    <span className="ibexa-label ibexa-label--checkbox-radio">Replace content</span>
                                </label>
                                <label className="form-check-inline ai-suggest-modal__mode-option">
                                    <input
                                        className="ibexa-input ibexa-input--radio"
                                        type="radio"
                                        name="ai-mode"
                                        value={SUGGEST_MODE.APPEND}
                                        checked={mode === SUGGEST_MODE.APPEND}
                                        onChange={() => setMode(SUGGEST_MODE.APPEND)}
                                    />
                                    <span className="ibexa-label ibexa-label--checkbox-radio">Append to content</span>
                                </label>
                            </div>

                            {/* Error */}
                            {error && (
                                <div className="ibexa-alert ibexa-alert--error ai-suggest-modal__error">
                                    <div className="ibexa-alert__content">
                                        <span className="ibexa-alert__title"><strong>Error:</strong> {error}</span>
                                    </div>
                                </div>
                            )}

                            {/* Preview */}
                            {suggestion && (
                                <div className="ai-suggest-modal__preview-section">
                                    <div className="ai-suggest-modal__preview-header">
                                        <span>Preview</span>
                                        <button
                                            className="ibexa-btn ibexa-btn--filled-info ibexa-btn--small"
                                            onClick={handleApply}
                                            type="button"
                                        >
                                            Apply
                                            <kbd className="ai-suggest-modal__kbd">⌘↵</kbd>
                                        </button>
                                    </div>
                                    <div className="ai-suggest-modal__preview">
                                        {fieldContext?.fieldType === 'ezrichtext' ? (
                                            <div dangerouslySetInnerHTML={{ __html: suggestion }} />
                                        ) : (
                                            <pre>{suggestion}</pre>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="modal-footer">
                            <button
                                className="ibexa-btn ibexa-btn--tertiary"
                                onClick={() => setOpen(false)}
                                type="button"
                            >Cancel</button>
                            <button
                                className="ibexa-btn ibexa-btn--primary"
                                onClick={handleGenerate}
                                disabled={(!streaming && loading) || !prompt.trim()}
                                type="button"
                            >
                                {streaming ? (
                                    <>
                                        <span className="ai-suggest-modal__streaming-indicator" />
                                        <span>Stop</span>
                                    </>
                                ) : loading ? (
                                    <span className="ai-suggest-modal__spinner" />
                                ) : (
                                    <>
                                        <svg className="ibexa-icon ibexa-icon--tiny-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
                                        </svg>
                                        <span className="ibexa-btn__label">{suggestion ? 'Regenerate' : 'Generate'}</span>
                                    </>
                                )}
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </>
    );
}

function mountAiSuggestModal() {
    let container = document.getElementById('ai-suggest-modal-root');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ai-suggest-modal-root';
        document.body.appendChild(container);
    }
    if (ReactDOM.createRoot) {
        ReactDOM.createRoot(container).render(<AiSuggestModal />);
    } else {
        ReactDOM.render(<AiSuggestModal />, container);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAiSuggestModal);
} else {
    mountAiSuggestModal();
}

export default AiSuggestModal;
