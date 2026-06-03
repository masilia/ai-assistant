import React, { useState } from 'react';
import { getProviderLabel } from './constants.js';
import ModelCard from './ModelCard.jsx';

export default function ProviderCard({
    provider,
    models,
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

    return (
        <div className={`ai-provider-card ${provider.isActive ? 'ai-provider-card--active' : ''}`}>
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
                    className={`ai-provider-card__chevron ${isExpanded ? 'ai-provider-card__chevron--open' : ''}`}
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
                        <span>{provider.apiUrl || 'Default endpoint'}</span>
                        <span>{providerModels.length} model{providerModels.length !== 1 ? 's' : ''}</span>
                    </div>
                </div>

                <div className="ai-provider-card__actions" onClick={(e) => e.stopPropagation()}>
                    <label className="ai-toggle" aria-label={`Toggle ${provider.name} active`}>
                        <input
                            type="checkbox"
                            checked={provider.isActive}
                            onChange={() => onActivateProvider(provider.id)}
                        />
                        <span className="ai-toggle__track" />
                    </label>

                    <button
                        type="button"
                        className="ai-icon-btn ai-icon-btn--test"
                        disabled={testingId === provider.id}
                        onClick={() => onTestProvider(provider.id)}
                    >
                        {testingId === provider.id ? '...' : '⚡ Test'}
                    </button>
                    <button type="button" className="ai-icon-btn" onClick={() => onEditProvider(provider)}>
                        Edit
                    </button>
                    <button type="button" className="ai-icon-btn ai-icon-btn--danger" onClick={() => onDeleteProvider(provider.id)}>
                        Delete
                    </button>
                </div>
            </div>

            {/* Test result toast */}
            {testResult && (
                <div style={{ padding: '0 20px 8px' }}>
                    <div className={`ai-test-toast ${testResult.success ? 'ai-test-toast--success' : 'ai-test-toast--error'}`}>
                        {testResult.success ? '✓' : '✕'} {testResult.message}
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
                                className="ai-provider-card__add-model-btn"
                                onClick={() => onAddModel(provider.id)}
                            >
                                + Add Model
                            </button>
                        </div>

                        {providerModels.length === 0 ? (
                            <div className="ai-empty-state" style={{ padding: '24px 0' }}>
                                <div className="ai-empty-state__icon">🤖</div>
                                <p className="ai-empty-state__title">No models yet</p>
                                <p className="ai-empty-state__desc">Add a model to start using this provider.</p>
                                <button
                                    type="button"
                                    className="ai-btn-add"
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
