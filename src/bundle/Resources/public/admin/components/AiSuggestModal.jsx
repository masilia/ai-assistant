import React, { useState, useEffect, useRef, useCallback } from 'react';
import ReactDOM from 'react-dom';
import { AI_ROUTES } from './ai-settings/api-routes.js';
import { QUICK_ACTIONS, applyQuickAction } from './ai-settings/constants.js';

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
    const [mode, setMode] = useState('replace');
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
            setMode('replace');
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

            while (true) {
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
                            break;
                        }

                        if (data.token) {
                            setSuggestion(prev => prev + data.token);
                        }

                        if (data.done) {
                            setStreaming(false);
                            break;
                        }
                    } catch (e) {
                        // Skip malformed JSON lines
                    }
                }

                if (!streaming) break;
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
    }, [fieldContext, streaming]);

    const handleApply = useCallback(() => {
        if (!suggestion || !onApplyRef.current) return;
        onApplyRef.current(suggestion, mode);
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
    };

    if (!open) return null;

    return (
        <>
            <div className="ai-suggest-overlay" onClick={() => setOpen(false)} />
            <div className="ai-suggest-modal" onKeyDown={handleKeyDown}>
                {/* Header */}
                <div className="ai-suggest-modal__header">
                    <div className="ai-suggest-modal__title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
                        </svg>
                        <span>AI Content Assistant</span>
                    </div>
                    <button
                        className="ai-suggest-modal__close"
                        onClick={() => setOpen(false)}
                        type="button"
                        aria-label="Close"
                    >×</button>
                </div>

                {/* Field info */}
                <div className="ai-suggest-modal__field-info">
                    <span className="ai-suggest-modal__field-name">{fieldContext?.fieldName || 'Field'}</span>
                    <span className="ai-suggest-modal__field-type">
                        {fieldTypeLabel[fieldContext?.fieldType] || fieldContext?.fieldType}
                    </span>
                </div>

                {/* Prompt input */}
                <div className="ai-suggest-modal__prompt-section">
                    <label className="ai-suggest-modal__label" htmlFor="ai-prompt-input">
                        What would you like to write?
                    </label>
                    <textarea
                        ref={promptRef}
                        id="ai-prompt-input"
                        className="ai-suggest-modal__textarea"
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
                                disabled={loading}
                                title={action.promptTemplate}
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
                        <label className="ai-suggest-modal__source-lang-label">
                            Source language code (e.g., eng-GB, fre-FR):
                        </label>
                        <div className="ai-suggest-modal__source-lang-row">
                            <input
                                type="text"
                                className="ai-suggest-modal__source-lang-input"
                                value={sourceLanguage}
                                onChange={(e) => setSourceLanguage(e.target.value)}
                                placeholder="eng-GB"
                                disabled={loading}
                            />
                            <button
                                type="button"
                                className="ai-suggest-modal__btn ai-suggest-modal__btn--primary"
                                onClick={() => handleSourceLanguageSubmit(sourceLanguage)}
                                disabled={!sourceLanguage.trim() || loading}
                            >
                                Use as Source
                            </button>
                            <button
                                type="button"
                                className="ai-suggest-modal__btn ai-suggest-modal__btn--ghost"
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
                    <label className={`ai-suggest-modal__mode-option ${mode === 'replace' ? 'ai-suggest-modal__mode-option--active' : ''}`}>
                        <input
                            type="radio"
                            name="ai-mode"
                            value="replace"
                            checked={mode === 'replace'}
                            onChange={() => setMode('replace')}
                        />
                        Replace content
                    </label>
                    <label className={`ai-suggest-modal__mode-option ${mode === 'append' ? 'ai-suggest-modal__mode-option--active' : ''}`}>
                        <input
                            type="radio"
                            name="ai-mode"
                            value="append"
                            checked={mode === 'append'}
                            onChange={() => setMode('append')}
                        />
                        Append to content
                    </label>
                </div>

                {/* Actions */}
                <div className="ai-suggest-modal__actions">
                    <button
                        className="ai-suggest-modal__btn ai-suggest-modal__btn--secondary"
                        onClick={() => setOpen(false)}
                        type="button"
                    >Cancel</button>
                    <button
                        className="ai-suggest-modal__btn ai-suggest-modal__btn--primary"
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
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
                                </svg>
                                {suggestion ? 'Regenerate' : 'Generate'}
                            </>
                        )}
                    </button>
                </div>

                {/* Error */}
                {error && (
                    <div className="ai-suggest-modal__error">
                        <strong>Error:</strong> {error}
                    </div>
                )}

                {/* Preview */}
                {suggestion && (
                    <div className="ai-suggest-modal__preview-section">
                        <div className="ai-suggest-modal__preview-header">
                            <span>Preview</span>
                            <button
                                className="ai-suggest-modal__btn ai-suggest-modal__btn--apply"
                                onClick={handleApply}
                                type="button"
                            >
                                Apply
                                <kbd>⌘↵</kbd>
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
