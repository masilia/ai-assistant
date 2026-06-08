import React, { useEffect, useState } from 'react';
import { AI_ROUTES } from './api-routes.js';
import { cleanErrorMessage } from './constants.js';

const STATE_LABELS = {
    not_configured: 'Not configured',
    online:         'Online',
    offline:        'Offline',
};

const STATE_DESCRIPTIONS = {
    not_configured: 'No active AI provider is configured. Add one to start using AI-assisted content editing.',
    online:         'The active provider is reachable and ready.',
    offline:        'The active provider is configured but unreachable. Check the connection.',
};

/**
 * ActiveBanner — shows the current state of the AI engine.
 *
 * Three visual states (matches the /api/health endpoint):
 *   - not_configured: gray dot, "Not configured", with CTA to add a provider
 *   - online:         green dot, "Online"
 *   - offline:        red dot, "Offline" + reason, with a "Test again" CTA
 *
 * The banner fetches /api/health on mount and exposes an imperative
 * refresh() so the parent can re-check after activating a provider.
 */
export default function ActiveBanner({ providers, models, activeProviderId, activeModelId, currentSiteaccess = 'default', onRefresh }) {
    const [health, setHealth] = useState(null);

    useEffect(() => {
        refresh();
    }, []);

    const refresh = () => {
        fetch(AI_ROUTES.health, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (data) setHealth(data);
            })
            .catch((err) => {
                // Silent: the banner already shows "Not configured" or the
                // last-known state. Operator can investigate the JS console.
                console.error('[AI] Health check failed:', cleanErrorMessage(err?.message));
            });
    };

    const state = health?.state ?? 'not_configured';
    const label = STATE_LABELS[state] ?? state;
    const description = health?.message ? `${STATE_DESCRIPTIONS[state]} (${health.message})` : STATE_DESCRIPTIONS[state];

    // Backwards-compat: when no health data is loaded yet but the
    // legacy activeProviderId/activeModelId indicate one, show 'online'.
    const legacyActive = !health && activeProviderId && providers.find((p) => p.id === activeProviderId);
    const effectiveState = legacyActive ? 'online' : state;

    return (
        <div
            className={`ibexa-alert ai-banner ai-banner--${effectiveState}`}
            role="status"
            aria-label="Active AI engine status"
        >
            <div className="ibexa-alert__content">
                <span className="ai-banner__label">
                    Currently Active LLM Engine
                    {currentSiteaccess && (
                        <small className="ai-banner__siteaccess">For siteaccess: <code>{currentSiteaccess}</code></small>
                    )}
                </span>
                <h4 className="ai-banner__title">
                    {health?.providerName ?? providers.find((p) => p.id === activeProviderId)?.name ?? 'None'}
                    <span className="ai-banner__divider">/</span>
                    {models.find((m) => m.id === activeModelId)?.name ?? 'No Active Model'}
                </h4>
                <p className="ai-banner__desc">{description}</p>
            </div>
            <div className={`ai-banner__status ai-banner__status--${effectiveState}`}>
                <span className={`ai-banner__dot ai-banner__dot--${effectiveState}`} />
                <span>{STATE_LABELS[effectiveState]}</span>
                {effectiveState !== 'not_configured' && (
                    <button
                        type="button"
                        className="ai-banner__refresh"
                        onClick={() => { refresh(); onRefresh && onRefresh(); }}
                        aria-label="Re-check connection"
                    >
                        ↻
                    </button>
                )}
            </div>
        </div>
    );
}
