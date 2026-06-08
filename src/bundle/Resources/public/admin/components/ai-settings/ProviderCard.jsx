import React, { useState } from 'react';
import { getProviderLabel } from './constants.js';
import ModelCard from './ModelCard.jsx';

export default function ProviderCard({
    provider,
    models,
    currentSiteaccess = '',
    isExpanded,
    onToggleExpand,
    onActivateProvider,
    onEditProvider,
    onDeleteProvider,
    onTestProvider,
    testingId,
    testResult,
    onActivateModel,
    onEditModel,
    onDeleteModel,
    onAddModel,
}) {
    const providerModels = models.filter(m => m.providerId === provider.id);

    // The row whose siteaccess matches the admin's current siteaccess
    // is the one actually driving requests. Highlight it; flag others
    // as 'not in your scope' so a misconfig is visually obvious.
    const matchesCurrentScope = provider.siteaccess === currentSiteaccess
        || (!provider.siteaccess && currentSiteaccess && false); // global never matches "your" scope as primary

    return (
        <div
            className={`ai-provider-card ${provider.isActive ? 'ai-provider-card--active' : ''} ${
                matchesCurrentScope ? 'ai-provider-card--your-scope' : ''
            }`}
            data-scope={provider.siteaccess || 'global'}
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
                <svg
                    className={`ibexa-icon ibexa-icon--small-medium ai-provider-card__chevron ${isExpanded ? 'ai-provider-card__chevron--open' : ''}`}
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <polyline points="9 6 15 12 9 18" />
                </svg>

                <div className="ai-provider-card__info">
                    <div className="ai-provider-card__name">{provider.name}</div>
                    <div className="ai-provider-card__meta">
                        <span className="ai-provider-card__type-badge">{getProviderLabel(provider.identifier)}</span>
                        <span className={`ai-provider-card__scope-badge ${provider.siteaccess ? 'ai-provider-card__scope-badge--scoped' : ''}`}>
                            {provider.siteaccess || 'Global'}
                        </span>
                        <span>{provider.apiUrl || 'Default endpoint'}</span>
                        <span>{providerModels.length} model{providerModels.length !== 1 ? 's' : ''}</span>
                    </div>
                </div>

                <div className="ai-provider-card__actions" onClick={(e) => e.stopPropagation()}>
                    <div
                        className={`ibexa-toggle ibexa-toggle--small ${provider.isActive ? 'ibexa-toggle--is-checked' : ''}`}
                        onClick={() => onActivateProvider(provider.id)}
                    >
                        <label className="ibexa-toggle__switcher" aria-label={`Toggle ${provider.name} active`}>
                            <input
                                className="ibexa-toggle__input"
                                type="checkbox"
                                checked={provider.isActive}
                                onChange={() => onActivateProvider(provider.id)}
                            />
                            <span className="ibexa-toggle__indicator" />
                        </label>
                    </div>

                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--secondary ibexa-btn--small"
                        disabled={testingId === provider.id}
                        onClick={() => onTestProvider(provider.id)}
                    >
                        {testingId === provider.id ? '...' : 'Test'}
                    </button>
                    <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text" onClick={() => onEditProvider(provider)} title="Edit">
                        <svg className="ibexa-icon ibexa-icon--small">
                            <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#edit" />
                        </svg>
                    </button>
                    <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text ai-btn--danger" onClick={() => onDeleteProvider(provider.id)} title="Delete">
                        <svg className="ibexa-icon ibexa-icon--small">
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
                        <span><strong>API Key:</strong> {provider.apiKey || 'Not set'}</span>
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
                            <div className="ai-empty-state">
                                <div className="ai-empty-state__icon">🤖</div>
                                <p className="ai-empty-state__title">No models yet</p>
                                <p className="ai-empty-state__desc">Add a model to start using this provider.</p>
                                <button
                                    type="button"
                                    className="ibexa-btn ibexa-btn--secondary"
                                    onClick={() => onAddModel(provider.id)}
                                >
                                    + Add First Model
                                </button>
                            </div>
                        ) : (
                            <div className="ai-provider-card__models-list">
                                {providerModels.map(m => (
                                    <ModelCard
                                        key={m.id}
                                        model={m}
                                        onActivate={onActivateModel}
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
