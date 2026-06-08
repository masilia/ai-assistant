import React from 'react';

/**
 * Suggestion preview panel: shows the streamed result and the Apply button.
 * For ezrichtext fields, renders HTML; otherwise renders pre-formatted text.
 */
export default function SuggestionPreview({ text, fieldType, onApply }) {
    if (!text) return null;

    return (
        <div className="ai-suggest-modal__preview-section">
            <div className="ai-suggest-modal__preview-header">
                <span>Preview</span>
                <button
                    className="ibexa-btn ibexa-btn--filled-info ibexa-btn--small"
                    onClick={onApply}
                    type="button"
                >
                    Apply
                    <kbd className="ai-suggest-modal__kbd">⌘↵</kbd>
                </button>
            </div>
            {/*
              aria-live="polite" announces streamed tokens to assistive tech
              without interrupting the current announcement. aria-atomic="false"
              so only newly appended nodes are read (not the whole preview).
            */}
            <div
                className="ai-suggest-modal__preview"
                aria-live="polite"
                aria-atomic="false"
                aria-label="AI suggestion preview"
            >
                {fieldType === 'ezrichtext' ? (
                    <div dangerouslySetInnerHTML={{ __html: text }} />
                ) : (
                    <pre>{text}</pre>
                )}
            </div>
        </div>
    );
}
