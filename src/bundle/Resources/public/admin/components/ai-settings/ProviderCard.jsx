import React, { useState } from 'react';
import { getProviderLabel } from './constants.js';
import { ChevronIcon, BotIcon } from './icons.jsx';
import EmptyState from './EmptyState.jsx';
import ModelCard from './ModelCard.jsx';
import IbexaDropdown from './IbexaDropdown.jsx';
import IbexaTagViewSelect from './IbexaTagViewSelect.jsx';

export default function ProviderCard({
    provider,
    models,
    siteaccesses,
    currentSiteaccess = '',
    isExpanded,
    onToggleExpand,
    onSetSiteaccesses,
    onSetChatModel,
    onSetImageModel,
    onEditProvider,
    onDeleteProvider,
    onTestProvider,
    testingId,
    testResult,
    onEditModel,
    onDeleteModel,
    onAddModel,
}) {
    const [apiKeyRevealed, setApiKeyRevealed] = useState(false);
    const providerModels = models.filter(m => m.providerId === provider.id);

    const matchesCurrentScope = provider.siteaccesses.includes(currentSiteaccess);

    const assignedBadge = provider.siteaccesses.length > 0
        ? provider.siteaccesses.join(', ')
        : 'No siteaccess assigned';

    const chatModelOptions = [
        { value: '', label: 'None' },
        ...providerModels.map(m => ({ value: m.id, label: `${m.name} (${m.identifier})` })),
    ];

    const imageModelOptions = [
        { value: '', label: 'None' },
        ...providerModels.filter(m => m.supportsImage).map(m => ({ value: m.id, label: `${m.name} (${m.identifier})` })),
    ];

    const siteaccessOptions = siteaccesses.map(sa => ({ value: sa, label: sa }));

    return (
        <div
            className={`ai-provider-card ${matchesCurrentScope ? 'ai-provider-card--your-scope' : ''}`}
            data-scope={provider.siteaccesses.length > 0 ? provider.siteaccesses.join(',') : 'none'}
        >
            {/* Clickable header */}
            <div
                className="ai-provider-card__header"
                onClick={onToggleExpand}
                role="button"
                aria-expanded={isExpanded}
                tabIndex={0}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onToggleExpand(); }}}
            >
                <ChevronIcon
                    size="small-medium"
                    className={`ai-provider-card__chevron ${isExpanded ? 'ai-provider-card__chevron--open' : ''}`}
                />

                <div className="ai-provider-card__info">
                    <div className="ai-provider-card__name">
                        {provider.name}
                        {testResult && (
                            <span
                                className={`ai-provider-card__health-dot ${testResult.success ? 'ai-provider-card__health-dot--ok' : 'ai-provider-card__health-dot--fail'}`}
                                title={testResult.success ? 'Connection healthy' : 'Connection failed'}
                                aria-label={testResult.success ? 'Connection healthy' : 'Connection failed'}
                            />
                        )}
                    </div>
                    <div className="ai-provider-card__meta">
                        <span className="ai-provider-card__type-badge">{getProviderLabel(provider.identifier)}</span>
                        <span className={`ai-provider-card__scope-badge ${provider.siteaccesses.length > 0 ? 'ai-provider-card__scope-badge--scoped' : ''}`}>
                            {assignedBadge}
                        </span>
                        <span>{provider.apiUrl || 'Default endpoint'}</span>
                        <span>{providerModels.length} model{providerModels.length !== 1 ? 's' : ''}</span>
                    </div>
                </div>

                <div className="ai-provider-card__actions" onClick={(e) => e.stopPropagation()}>
                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--secondary ibexa-btn--small"
                        disabled={testingId === provider.id}
                        onClick={() => onTestProvider(provider.id)}
                    >
                        {testingId === provider.id ? '...' : 'Test'}
                    </button>
                    <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text" onClick={() => onEditProvider(provider)} title={`Edit ${provider.name}`} aria-label={`Edit ${provider.name}`}>
                        <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                            <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#edit" />
                        </svg>
                    </button>
                    <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text ai-btn--danger" onClick={() => onDeleteProvider(provider.id)} title={`Delete ${provider.name}`} aria-label={`Delete ${provider.name}`}>
                        <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                            <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#trash" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* Test result toast */}
            {testResult && (
                <div className="ai-provider-card__toast-wrapper">
                    <div className={`ibexa-alert ${testResult.success ? 'ibexa-alert--success' : 'ibexa-alert--error'} ai-test-toast`}>
                        <div className="ibexa-alert__content">
                            <span className="ibexa-alert__title">{testResult.success ? '✓' : '✕'} {testResult.message}</span>
                        </div>
                    </div>
                </div>
            )}

            {/* Collapsible body */}
            <div className={`ai-provider-card__body-wrapper ${isExpanded ? 'ai-provider-card__body-wrapper--open' : ''}`}>
                <div className="ai-provider-card__body">
                    {/* Details row */}
                    <div className="ai-provider-card__details">
                        <span><strong>Endpoint:</strong> {provider.apiUrl || 'Default'}</span>
                        <span>
                            <strong>API Key:</strong>{' '}
                            {provider.apiKey ? (
                                <>
                                    <span className="ai-provider-card__api-key-value">
                                        {apiKeyRevealed ? provider.apiKey : '••••••••••••'}
                                    </span>
                                    <button
                                        type="button"
                                        className="ai-provider-card__api-key-toggle"
                                        onClick={() => setApiKeyRevealed(v => !v)}
                                        aria-label={apiKeyRevealed ? 'Hide API key' : 'Show API key'}
                                    >
                                        {apiKeyRevealed ? 'Hide' : 'Show'}
                                    </button>
                                </>
                            ) : 'Not set'}
                        </span>
                    </div>

                    {/* Configuration section */}
                    <div className="ai-provider-card__config-section">
                        {/* Siteaccess assignments */}
                        <div className="ai-provider-card__config-row">
                            <IbexaTagViewSelect
                                label="Assigned Siteaccesses"
                                values={provider.siteaccesses}
                                options={siteaccessOptions}
                                placeholder="No siteaccess assigned"
                                onChange={(next) => onSetSiteaccesses(provider.id, next)}
                            />
                        </div>

                        {/* Active model selectors */}
                        <div className="ai-provider-card__config-row ai-provider-card__config-row--inline">
                            <IbexaDropdown
                                label="Chat Model"
                                id={`chat-model-${provider.id}`}
                                value={provider.activeChatModelId ?? ''}
                                options={chatModelOptions}
                                placeholder="None"
                                onChange={(val) => onSetChatModel(provider.id, val ? parseInt(val, 10) : null)}
                            />
                            <IbexaDropdown
                                label="Image Model"
                                id={`image-model-${provider.id}`}
                                value={provider.activeImageModelId ?? ''}
                                options={imageModelOptions}
                                placeholder="None"
                                onChange={(val) => onSetImageModel(provider.id, val ? parseInt(val, 10) : null)}
                            />
                        </div>
                    </div>

                    {/* Models section */}
                    <div className="ai-provider-card__models-section">
                        <div className="ai-provider-card__models-header">
                            <h6>Models ({providerModels.length})</h6>
                            <button
                                type="button"
                                className="ibexa-btn ibexa-btn--secondary ibexa-btn--small"
                                onClick={() => onAddModel(provider.id)}
                            >
                                + Add Model
                            </button>
                        </div>

                        {providerModels.length === 0 ? (
                            <EmptyState
                                icon={BotIcon}
                                title="No models yet"
                                description="Add a model to start using this provider."
                                ctaLabel="+ Add First Model"
                                ctaVariant="secondary"
                                onCta={() => onAddModel(provider.id)}
                            />
                        ) : (
                            <div className="ai-provider-card__models-list">
                                {providerModels.map(m => (
                                    <ModelCard
                                        key={m.id}
                                        model={m}
                                        isChatActive={m.id === provider.activeChatModelId}
                                        isImageActive={m.id === provider.activeImageModelId}
                                        onEdit={onEditModel}
                                        onDelete={onDeleteModel}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
