import React from 'react';

/**
 * Source-language picker for the translation quick action.
 *
 * Confirmed language is reported via onSubmit(code). When the `languages`
 * prop is non-empty, renders a <select>; otherwise falls back to a free-text
 * <input> for backwards compatibility (e.g. when the backend endpoint
 * isn't reachable or returns nothing).
 */
export default function SourceLanguageInput({ value, onChange, onSubmit, onCancel, disabled, languages = [] }) {
    const hasOptions = Array.isArray(languages) && languages.length > 0;

    return (
        <div className="ai-suggest-modal__source-lang">
            <label className="ibexa-label" htmlFor="ai-source-lang-input">
                Source language:
            </label>
            <div className="ai-suggest-modal__source-lang-row">
                {hasOptions ? (
                    <select
                        id="ai-source-lang-input"
                        className="ibexa-input form-control"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        disabled={disabled}
                    >
                        <option value="" disabled>— Select a language —</option>
                        {languages.map((lang) => (
                            <option key={lang.code} value={lang.code}>
                                {lang.name} ({lang.code})
                            </option>
                        ))}
                    </select>
                ) : (
                    <input
                        id="ai-source-lang-input"
                        type="text"
                        className="ibexa-input ibexa-input--text form-control"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        placeholder="eng-GB"
                        disabled={disabled}
                    />
                )}
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
