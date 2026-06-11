import React, { useState, useEffect, useRef, useCallback } from 'react';
import ReactDOM from 'react-dom';
import { SUGGEST_MODE, applyQuickAction, notify, cleanErrorMessage } from './ai-settings/constants.js';
import { AI_ROUTES } from './ai-settings/api-routes.js';
import { useAiStream } from './AiSuggestModal/useAiStream.js';
import PromptSection from './AiSuggestModal/PromptSection.jsx';
import QuickActions from './AiSuggestModal/QuickActions.jsx';
import SourceLanguageInput from './AiSuggestModal/SourceLanguageInput.jsx';
import ModeSelector from './AiSuggestModal/ModeSelector.jsx';
import AspectRatioSelector from './AiSuggestModal/AspectRatioSelector.jsx';
import ErrorBanner from './AiSuggestModal/ErrorBanner.jsx';
import SuggestionPreview from './AiSuggestModal/SuggestionPreview.jsx';
import { SparklesIcon, BrainIcon, CloseIcon } from './ai-settings/icons.jsx';

/**
 * @typedef {import('./ai-settings/types.js').FieldContext} FieldContext
 */

const FIELD_TYPE_LABELS = {
    ezstring: 'Text Line',
    eztext: 'Text Block',
    ezrichtext: 'Rich Text',
    novaseometas: 'SEO Metas',
    ezmatrix: 'Matrix',
    ezimage: 'Image',
};

/**
 * AI Suggest Modal — React component for the AI content assistant prompt/preview UI.
 *
 * Listens for 'ai-suggest:open' custom events dispatched by ai-suggest-button.js
 * and renders a floating modal with prompt input, preview, and apply controls.
 *
 * Composed of focused subcomponents in ./AiSuggestModal/. SSE streaming is
 * delegated to the useAiStream hook.
 */
function AiSuggestModal() {
    const [open, setOpen] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [mode, setMode] = useState(SUGGEST_MODE.REPLACE);
    const [fieldContext, setFieldContext] = useState(/** @type {FieldContext|null} */ (null));
    const [selectedQuickAction, setSelectedQuickAction] = useState(null);
    const [sourceLanguage, setSourceLanguage] = useState('');
    const [showSourceLangInput, setShowSourceLangInput] = useState(false);
    const [availableLanguages, setAvailableLanguages] = useState(/** @type {Array<{code: string, name: string}>} */ ([]));
    const [imageGenLoading, setImageGenLoading] = useState(false);
    const [imageGenResult, setImageGenResult] = useState(/** @type {{ imageData: string, mimeType: string } | null} */ (null));

    const promptRef = useRef(null);
    const onApplyRef = useRef(null);

    const stream = useAiStream(fieldContext, prompt, sourceLanguage);

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
            setMode(SUGGEST_MODE.REPLACE);
            setSelectedQuickAction(null);
            setSourceLanguage('');
            setShowSourceLangInput(false);
            setImageGenLoading(false);
            setImageGenResult(null);
            stream.stop();
            stream.clear();

            // Pre-fill hints sent by the injector (e.g. one-click
            // 'Translate from {Language}' buttons on translated
            // sibling fields). Non-translate hints are no-ops.
            if (detail.hintAction === 'translate' && detail.hintSourceLanguage) {
                setShowSourceLangInput(true);
                setSourceLanguage(detail.hintSourceLanguage);
            }
        };

        document.addEventListener('ai-suggest:open', handler);
        return () => document.removeEventListener('ai-suggest:open', handler);
    }, [stream]);

    // Fetch available languages lazily when the source-language picker
    // is first shown. Cached in state so we don't re-hit the endpoint
    // on every modal open.
    //
    // The endpoint path includes the contentId (Symfony routing
    // pattern param: /admin/api/ai/languages/{contentId}). When no
    // contentId is available yet (e.g. a new unsaved draft), the
    // fetch is skipped and the modal falls back to its free-text
    // input.
    useEffect(() => {
        if (!showSourceLangInput || availableLanguages.length > 0) return;

        const contentId = fieldContext?.contentId;
        if (!contentId) return;

        const url = AI_ROUTES.languages(contentId);

        fetch(url, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (data && Array.isArray(data.languages)) {
                    setAvailableLanguages(data.languages);
                }
            })
            .catch(() => { /* fallback to free-text input */ });
    }, [showSourceLangInput, availableLanguages.length, fieldContext?.contentId]);

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
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [open]);

    const handleGenerate = useCallback(() => {
        if (stream.streaming) {
            stream.stop();
        } else {
            stream.start();
        }
    }, [stream]);

    const handleApply = useCallback(() => {
        // Image generation: inject the generated image into the file picker
        if (imageGenResult && onApplyRef.current) {
            const result = onApplyRef.current(imageGenResult, 'image');
            if (result && result.success === false) {
                stream.setError(result.error || 'Failed to apply the image.');
                return;
            }
            setOpen(false);
            return;
        }

        if (!stream.suggestion || !onApplyRef.current) return;
        const result = onApplyRef.current(stream.suggestion, mode);
        if (result && result.success === false) {
            stream.setError(result.error || 'Failed to apply the suggestion.');
            return;
        }
        setOpen(false);
    }, [stream, mode, imageGenResult]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            if (stream.suggestion || imageGenResult) {
                handleApply();
            } else {
                handleGenerate();
            }
        }
    }, [stream.suggestion, imageGenResult, handleApply, handleGenerate]);

    const handleImageGeneration = useCallback(async (selectedAspectRatio) => {
        setImageGenLoading(true);
        setImageGenResult(null);
        stream.setError(null);

        // Build a contextual prompt that includes field/content info
        const contextParts = [];
        if (fieldContext?.contentTypeName) {
            contextParts.push(fieldContext.contentTypeName);
        }
        if (fieldContext?.contentTitle) {
            contextParts.push(`"${fieldContext.contentTitle}"`);
        }
        if (fieldContext?.fieldName) {
            contextParts.push(`for the "${fieldContext.fieldName}" field`);
        }

        const contextPrefix = contextParts.length > 0 ? contextParts.join(' ') + ' — ' : '';
        const userPrompt = prompt.trim();
        const finalPrompt = userPrompt
            ? contextPrefix + userPrompt
            : contextPrefix + 'Generate a relevant image for this content';

        try {
            const response = await fetch(AI_ROUTES.generateImage, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    prompt: finalPrompt,
                    size: selectedAspectRatio || '1:1',
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Image generation failed.');
            }

            setImageGenResult({
                imageData: data.imageData,
                mimeType: data.mimeType || 'image/png',
            });
        } catch (err) {
            stream.setError(cleanErrorMessage(err.message || 'Image generation failed.'));
        } finally {
            setImageGenLoading(false);
        }
    }, [prompt, fieldContext, stream]);

    const handleQuickAction = useCallback((quickAction) => {
        setSelectedQuickAction(quickAction.id);

        if (quickAction.isTranslation) {
            setShowSourceLangInput(true);
            return;
        }

        if (quickAction.isImageGeneration) {
            return;
        }

        const actionPrompt = applyQuickAction(quickAction.id, fieldContext);
        setPrompt(actionPrompt);
    }, [fieldContext]);

    const handleSourceLanguageSubmit = useCallback((lang) => {
        if (!lang || !fieldContext) return;
        if (lang === fieldContext.language) {
            stream.setError('Source language cannot be the same as the target language.');
            return;
        }
        setSourceLanguage(lang);
        setShowSourceLangInput(false);
        setPrompt(`Translate from ${lang} to ${fieldContext.language}`);
    }, [fieldContext, stream]);

    const handlePromptChange = useCallback((e) => {
        setPrompt(e.target.value);
        setSelectedQuickAction(null);
    }, []);

    const isNovaSeo = fieldContext?.fieldType === 'novaseometas';
    const generateButtonDisabled = (!stream.streaming && stream.loading) || imageGenLoading || !prompt.trim();

    if (!open) return null;

    return (
        <>
            <div className="ai-suggest-overlay" onClick={() => setOpen(false)} />

            <div
                className="ibexa-modal ai-suggest-modal"
                onKeyDown={handleKeyDown}
                role="dialog"
                aria-modal="true"
                aria-labelledby="ai-suggest-modal-title"
                aria-busy={stream.streaming || stream.loading}
            >
                <div className="modal-dialog">
                    <div className="modal-content">

                        <div className="modal-header">
                            <h5 className="modal-title ai-suggest-modal__title" id="ai-suggest-modal-title">
                                <SparklesIcon size="small-medium" className="ai-suggest-modal__title-icon" />
                                AI Content Assistant
                            </h5>
                            <button
                                className="close ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-btn--small"
                                onClick={() => setOpen(false)}
                                type="button"
                                aria-label="Close"
                            >
                                <CloseIcon size="small" />
                            </button>
                        </div>

                        <div className="ai-suggest-modal__field-info">
                            <span className="ai-suggest-modal__field-name">{fieldContext?.fieldName || 'Field'}</span>
                            <span className="ai-suggest-modal__field-type">
                                {FIELD_TYPE_LABELS[fieldContext?.fieldType] || fieldContext?.fieldType}
                            </span>
                        </div>

                        <div className="modal-body">
                            <PromptSection
                                value={prompt}
                                onChange={handlePromptChange}
                                disabled={stream.loading}
                                inputRef={promptRef}
                            />

                            <QuickActions
                                selectedId={selectedQuickAction}
                                onSelect={handleQuickAction}
                                disabled={stream.loading}
                                isTranslationDisabled={isNovaSeo}
                            />

                            <AspectRatioSelector
                                visible={selectedQuickAction === 'generate_image'}
                                onSelect={handleImageGeneration}
                            />

                            {showSourceLangInput && (
                                <SourceLanguageInput
                                    value={sourceLanguage}
                                    onChange={setSourceLanguage}
                                    onSubmit={handleSourceLanguageSubmit}
                                    onCancel={() => {
                                        setShowSourceLangInput(false);
                                        setSelectedQuickAction(null);
                                    }}
                                    disabled={stream.loading}
                                    languages={availableLanguages}
                                    currentLanguage={fieldContext?.language || ''}
                                />
                            )}

                            <ModeSelector value={mode} onChange={setMode} />

                            <ErrorBanner error={stream.error} />

                            <SuggestionPreview
                                text={stream.suggestion}
                                fieldType={fieldContext?.fieldType}
                                onApply={handleApply}
                                imageGenResult={imageGenResult}
                            />
                        </div>

                        <div className="modal-footer">
                            <button
                                className="ibexa-btn ibexa-btn--tertiary"
                                onClick={() => setOpen(false)}
                                type="button"
                            >Cancel</button>
                            <button
                                className={`ibexa-btn ibexa-btn--primary ${stream.streaming || stream.loading || imageGenLoading ? 'ai-suggest-modal__generate-btn--active' : ''}`}
                                onClick={handleGenerate}
                                disabled={generateButtonDisabled}
                                type="button"
                            >
                                {stream.streaming ? (
                                    <>
                                        <BrainIcon size={16} className="ibexa-icon--small ai-suggest-modal__brain-icon--pulse" />
                                        <span>Stop</span>
                                        <span className="visually-hidden">AI is generating content, please wait.</span>
                                    </>
                                ) : stream.loading || imageGenLoading ? (
                                    <>
                                        <BrainIcon size={16} className="ibexa-icon--small ai-suggest-modal__brain-icon--pulse" />
                                        <span className="visually-hidden">Loading, please wait.</span>
                                    </>
                                ) : (
                                    <>
                                        <SparklesIcon size={16} className="ibexa-icon--small" />
                                        <span className="ibexa-btn__label">{stream.suggestion || imageGenResult ? 'Regenerate' : 'Generate'}</span>
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAiSuggestModal);
} else {
    mountAiSuggestModal();
}

export default AiSuggestModal;
