import React from 'react';

/**
 * Error banner. Renders nothing when error is empty.
 */
export default function ErrorBanner({ error }) {
    if (!error) return null;

    return (
        <div className="ibexa-alert ibexa-alert--error ai-suggest-modal__error">
            <div className="ibexa-alert__content">
                <span className="ibexa-alert__title"><strong>Error:</strong> {error}</span>
            </div>
        </div>
    );
}
