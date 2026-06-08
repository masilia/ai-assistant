import React from 'react';

/**
 * Source-language input for the translation quick action.
 * Confirmed language is reported via onSubmit(lang).
 */
export default function SourceLanguageInput({ value, onChange, onSubmit, onCancel, disabled }) {
    return (
        <div className="ai-suggest-modal__source-lang">
            <label className="ibexa-label">
                Source language code (e.g., eng-GB, fre-FR):
            </label>
            <div className="ai-suggest-modal__source-lang-row">
                <input
                    type="text"
                    className="ibexa-input ibexa-input--text form-control"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="eng-GB"
                    disabled={disabled}
                />
                <button
                    type="button"
                    className="ibexa-btn ibexa-btn--primary ibexa-btn--small"
                    onClick={() => onSubmit(value)}
                    disabled={!value.trim() || disabled}
                >
                    Use as Source
                </button>
                <button
                    type="button"
                    className="ibexa-btn ibexa-btn--ghost ibexa-btn--small"
                    onClick={onCancel}
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}
