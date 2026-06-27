import React, { useState, useRef, useEffect } from 'react';

/**
 * The prompt textarea + label. Stateless: forwards value/onChange.
 * Shows a "Recent" dropdown with previously used prompts from localStorage.
 */
export default function PromptSection({ value, onChange, disabled, inputRef, recentPrompts = [] }) {
    const [showRecent, setShowRecent] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        if (!showRecent) return;
        const handler = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setShowRecent(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [showRecent]);

    return (
        <div className="ai-suggest-modal__section">
            <div className="ai-suggest-modal__prompt-header">
                <label className="ibexa-label" htmlFor="ai-prompt-input">
                    What would you like to write?
                </label>
                {recentPrompts.length > 0 && (
                    <div className="ai-suggest-modal__recent" ref={dropdownRef}>
                        <button
                            type="button"
                            className="ai-suggest-modal__recent-toggle"
                            onClick={() => setShowRecent(v => !v)}
                            disabled={disabled}
                        >
                            Recent
                        </button>
                        {showRecent && (
                            <div className="ai-suggest-modal__recent-menu" role="listbox" aria-label="Recent prompts">
                                {recentPrompts.map((p, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        className="ai-suggest-modal__recent-item"
                                        role="option"
                                        aria-selected="false"
                                        onClick={() => {
                                            onChange({ target: { value: p } });
                                            setShowRecent(false);
                                            inputRef?.current?.focus();
                                        }}
                                        title={p}
                                    >
                                        {p.length > 60 ? p.slice(0, 57) + '…' : p}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
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
