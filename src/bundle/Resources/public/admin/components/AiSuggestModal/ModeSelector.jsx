import React from 'react';
import { SUGGEST_MODE } from '../../ai-settings/constants.js';

/**
 * Replace vs. Append radio selector. Stateless.
 */
export default function ModeSelector({ value, onChange }) {
    return (
        <div className="ai-suggest-modal__mode">
            <label className="form-check-inline ai-suggest-modal__mode-option">
                <input
                    className="ibexa-input ibexa-input--radio"
                    type="radio"
                    name="ai-mode"
                    value={SUGGEST_MODE.REPLACE}
                    checked={value === SUGGEST_MODE.REPLACE}
                    onChange={() => onChange(SUGGEST_MODE.REPLACE)}
                />
                <span className="ibexa-label ibexa-label--checkbox-radio">Replace content</span>
            </label>
            <label className="form-check-inline ai-suggest-modal__mode-option">
                <input
                    className="ibexa-input ibexa-input--radio"
                    type="radio"
                    name="ai-mode"
                    value={SUGGEST_MODE.APPEND}
                    checked={value === SUGGEST_MODE.APPEND}
                    onChange={() => onChange(SUGGEST_MODE.APPEND)}
                />
                <span className="ibexa-label ibexa-label--checkbox-radio">Append to content</span>
            </label>
        </div>
    );
}
