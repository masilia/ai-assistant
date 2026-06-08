import React from 'react';

/**
 * The prompt textarea + label. Stateless: forwards value/onChange.
 */
export default function PromptSection({ value, onChange, disabled, inputRef }) {
    return (
        <div className="ai-suggest-modal__section">
            <label className="ibexa-label" htmlFor="ai-prompt-input">
                What would you like to write?
            </label>
            <textarea
                ref={inputRef}
                id="ai-prompt-input"
                className="ibexa-input ibexa-input--textarea form-control"
                value={value}
                onChange={onChange}
                placeholder="e.g. Write a 2-paragraph introduction about renewable energy in the Mediterranean..."
                rows={3}
                disabled={disabled}
            />
        </div>
    );
}
