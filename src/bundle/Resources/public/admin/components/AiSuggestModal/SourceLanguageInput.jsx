import React from 'react';

/**
 * Source-language picker for the translation quick action.
 *
 * Confirmed language is reported via onSubmit(code). When the `languages`
 * prop is non-empty, renders a <select>; otherwise falls back to a free-text
 * <input> for backwards compatibility (e.g. when the backend endpoint
 * isn't reachable or returns nothing).
 *
 * The current modification language (the target of the translation) is
 * filtered out from the dropdown so the user cannot pick the same
 * language as source and target.
 */
export default function SourceLanguageInput({ value, onChange, onSubmit, onCancel, disabled, languages = [], currentLanguage = '' }) {
    const hasOptions = Array.isArray(languages) && languages.length > 0;

    const filteredLanguages = hasOptions
        ? languages.filter((lang) => lang.code !== currentLanguage)
        : [];

    return (
        <div className="ai-suggest-modal__source-lang">
            <label className="ibexa-label" htmlFor="ai-source-lang-input">
                Translate from:
            </label>
            {currentLanguage && (
                <span className="ai-suggest-modal__source-lang-hint">
                    Target: {currentLanguage}
                </span>
            )}
            <div className="ai-suggest-modal__source-lang-row">
                {filteredLanguages.length > 0 ? (
                    <select
                        id="ai-source-lang-input"
                        className="ibexa-input form-control"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        disabled={disabled}
                    >
                        <option value="" disabled>— Select a language —</option>
                        {filteredLanguages.map((lang) => (
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
                        placeholder="e.g. fre-FR"
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
