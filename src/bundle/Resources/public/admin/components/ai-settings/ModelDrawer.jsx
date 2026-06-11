import React from 'react';
import { CloseIcon } from './icons.jsx';

/**
 * ModelDrawer — slide-over form for creating/editing model configs.
 * When `preselectedProviderId` is set (from clicking "+ Add Model" inside a card),
 * the provider is pre-selected and shown as read-only context.
 */
export default function ModelDrawer({ model, providers, preselectedProviderId, onClose, onSave, submitting }) {
    const isNew = model === 'new';
    const resolvedProviderId = preselectedProviderId || (isNew ? '' : model.providerId);
    const preselectedProvider = providers.find(p => p.id === preselectedProviderId);

    return (
        <div className="ai-drawer-overlay" onClick={onClose}>
            <div className="ibexa-side-panel ai-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Model configuration">
                <div className="ibexa-side-panel__header">
                    <h4 className="ai-drawer__title">{isNew ? 'New Model' : 'Edit Model'}</h4>
                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-btn--small"
                        onClick={onClose}
                        aria-label="Close"
                    >
                        <CloseIcon size="small" />
                    </button>
                </div>
                <div className="ibexa-side-panel__content">
                    <form className="ai-drawer__form" onSubmit={onSave}>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="model-provider">Provider *</label>
                            {preselectedProvider ? (
                                <>
                                    <input type="hidden" name="providerId" value={preselectedProviderId} />
                                    <input
                                        id="model-provider"
                                        className="ibexa-input ibexa-input--text form-control"
                                        type="text"
                                        value={`${preselectedProvider.name} (${preselectedProvider.identifier})`}
                                        readOnly
                                        disabled
                                    />
                                </>
                            ) : (
                                <select
                                    id="model-provider"
                                    className="ibexa-input form-control"
                                    name="providerId"
                                    defaultValue={resolvedProviderId}
                                    required
                                >
                                    <option value="" disabled>— Select Provider —</option>
                                    {providers.map(p => (
                                        <option key={p.id} value={p.id}>{p.name} ({p.identifier})</option>
                                    ))}
                                </select>
                            )}
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="model-name">Display Name *</label>
                            <input
                                id="model-name"
                                className="ibexa-input ibexa-input--text form-control"
                                type="text"
                                name="name"
                                defaultValue={isNew ? '' : model.name}
                                required
                                placeholder="e.g. GPT-4o, Claude Sonnet"
                            />
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="model-identifier">API Model Identifier *</label>
                            <input
                                id="model-identifier"
                                className="ibexa-input ibexa-input--text form-control"
                                type="text"
                                name="identifier"
                                defaultValue={isNew ? '' : model.identifier}
                                required
                                placeholder="e.g. gpt-4o, claude-3-5-sonnet-20241022"
                            />
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="model-temp">Temperature *</label>
                            <input
                                id="model-temp"
                                className="ibexa-input ibexa-input--text form-control"
                                type="number"
                                name="temperature"
                                step="0.1"
                                min="0.0"
                                max="2.0"
                                defaultValue={isNew ? 0.7 : model.temperature}
                                required
                            />
                            <small className="ai-drawer__hint">0.0 = concise/factual → 2.0 = creative/dynamic</small>
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="model-tokens">Max Tokens *</label>
                            <input
                                id="model-tokens"
                                className="ibexa-input ibexa-input--text form-control"
                                type="number"
                                name="maxTokens"
                                min="1"
                                defaultValue={isNew ? 2048 : model.maxTokens}
                                required
                            />
                        </div>
                        <div className="ai-drawer__field">
                            <label className="form-check-inline ai-drawer__checkbox-row">
                                <input
                                    className="ibexa-input ibexa-input--checkbox"
                                    type="checkbox"
                                    name="supportsImage"
                                    value="true"
                                    defaultChecked={isNew ? false : model.supportsImage}
                                />
                                <span className="ibexa-label ibexa-label--checkbox-radio">Supports Image Generation</span>
                            </label>
                            <small className="ai-drawer__hint">Enable this if the model can generate images (e.g. gpt-image-2, image-01).</small>
                        </div>
                        <div className="ai-drawer__actions">
                            <button type="submit" className="ibexa-btn ibexa-btn--primary" disabled={submitting}>
                                {submitting ? 'Saving...' : 'Save Config'}
                            </button>
                            <button type="button" className="ibexa-btn ibexa-btn--tertiary" onClick={onClose}>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
