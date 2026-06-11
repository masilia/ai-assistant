import React, { useEffect, useState } from 'react';
import { AI_ROUTES } from './api-routes.js';
import { cleanErrorMessage } from './constants.js';

/**
 * ActiveBanner — shows the current state of the AI engine for the
 * active siteaccess. Displays chat provider/model, image provider/model,
 * and contextual guidance per state.
 */
export default function ActiveBanner({ providers, models, currentSiteaccess = 'default', onRefresh }) {
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
                console.error('[AI] Health check failed:', cleanErrorMessage(err?.message));
            });
    };

    // Resolve active provider for this siteaccess
    const chatProvider = providers.find(
        (p) => p.siteaccesses.includes(currentSiteaccess) && p.activeChatModelId
    );
    const chatModel = chatProvider
        ? models.find((m) => m.id === chatProvider.activeChatModelId)
        : null;

    const imageProvider = providers.find(
        (p) => p.siteaccesses.includes(currentSiteaccess) && p.activeImageModelId
    );
    const imageModel = imageProvider
        ? models.find((m) => m.id === imageProvider.activeImageModelId)
        : null;

    const hasChat = chatProvider && chatModel;
    const hasImage = imageProvider && imageModel;

    // Determine state
    const state = health?.state ?? (hasChat ? 'online' : 'not_configured');

    const renderContent = () => {
        if (state === 'online' && hasChat) {
            return (
                <>
                    <div className="ai-banner__row">
                        <span className="ai-banner__label">Chat</span>
                        <span className="ai-banner__value">
                            {chatProvider.name}
                            <span className="ai-banner__divider"> / </span>
                            {chatModel.name}
                        </span>
                    </div>
                    {hasImage && (
                        <div className="ai-banner__row">
                            <span className="ai-banner__label">Image</span>
                            <span className="ai-banner__value">
                                {imageProvider.name}
                                <span className="ai-banner__divider"> / </span>
                                {imageModel.name}
                            </span>
                        </div>
                    )}
                    {!hasImage && (
                        <div className="ai-banner__row">
                            <span className="ai-banner__label">Image</span>
                            <span className="ai-banner__value ai-banner__value--muted">Not configured</span>
                        </div>
                    )}
                    {health?.message && (
                        <p className="ai-banner__desc">{health.message}</p>
                    )}
                </>
            );
        }

        if (state === 'offline') {
            return (
                <>
                    <div className="ai-banner__row">
                        <span className="ai-banner__label">Provider</span>
                        <span className="ai-banner__value">{health?.providerName ?? 'Unknown'}</span>
                    </div>
                    <p className="ai-banner__desc ai-banner__desc--error">
                        {health?.message ?? 'Connection failed.'}
                    </p>
                    <p className="ai-banner__desc">
                        Verify the API key and endpoint URL in the provider settings, then test the connection.
                    </p>
                </>
            );
        }

        // not_configured
        const assignedCount = providers.filter((p) => p.siteaccesses.includes(currentSiteaccess)).length;
        const configuredCount = providers.filter(
            (p) => p.siteaccesses.includes(currentSiteaccess) && p.activeChatModelId
        ).length;

        if (assignedCount === 0) {
            return (
                <p className="ai-banner__desc">
                    No providers are assigned to <code>{currentSiteaccess}</code>.
                    Add a provider and assign it to this siteaccess, then select a chat model.
                </p>
            );
        }

        if (configuredCount === 0) {
            return (
                <p className="ai-banner__desc">
                    {assignedCount} provider{assignedCount !== 1 ? 's' : ''} assigned to <code>{currentSiteaccess}</code>,
                    but none have a chat model selected. Open a provider card and choose a chat model.
                </p>
            );
        }

        return (
            <p className="ai-banner__desc">
                No active AI provider found for <code>{currentSiteaccess}</code>.
            </p>
        );
    };

    return (
        <div
            className={`ibexa-alert ai-banner ai-banner--${state}`}
            role="status"
            aria-label="Active AI engine status"
        >
            <div className="ibexa-alert__content">
                <span className="ai-banner__title-row">
                    <span className="ai-banner__title">AI Engine</span>
                    <small className="ai-banner__siteaccess">
                        siteaccess: <code>{currentSiteaccess}</code>
                    </small>
                </span>
                <div className="ai-banner__body">
                    {renderContent()}
                </div>
            </div>
            <div className={`ai-banner__status ai-banner__status--${state}`}>
                <span className={`ai-banner__dot ai-banner__dot--${state}`} />
                <span>{state === 'online' ? 'Online' : state === 'offline' ? 'Offline' : 'Not Configured'}</span>
                {state !== 'not_configured' && (
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
