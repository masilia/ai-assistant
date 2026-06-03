import React from 'react';

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
            <div className="ai-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Model configuration">
                <div className="ai-drawer__header">
                    <h4>{isNew ? 'New Model' : 'Edit Model'}</h4>
                    <button type="button" className="ai-drawer__close" onClick={onClose} aria-label="Close">&times;</button>
                </div>
                <form className="ai-drawer__form" onSubmit={onSave}>
                    <div className="ai-drawer__field">
                        <label htmlFor="model-provider">Provider *</label>
                        {preselectedProvider ? (
                            <>
                                <input type="hidden" name="providerId" value={preselectedProviderId} />
                                <input
                                    id="model-provider"
                                    type="text"
                                    value={`${preselectedProvider.name} (${preselectedProvider.identifier})`}
                                    readOnly
                                    style={{ background: 'hsl(220, 26%, 96%)', cursor: 'default' }}
                                />
                            </>
                        ) : (
                            <select
                                id="model-provider"
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
                        <label htmlFor="model-name">Display Name *</label>
                        <input
                            id="model-name"
                            type="text"
                            name="name"
                            defaultValue={isNew ? '' : model.name}
                            required
                            placeholder="e.g. GPT-4o, Claude Sonnet"
                        />
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="model-identifier">API Model Identifier *</label>
                        <input
                            id="model-identifier"
                            type="text"
                            name="identifier"
                            defaultValue={isNew ? '' : model.identifier}
                            required
                            placeholder="e.g. gpt-4o, claude-3-5-sonnet-20241022"
                        />
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="model-temp">Temperature *</label>
                        <input
                            id="model-temp"
                            type="number"
                            name="temperature"
                            step="0.1"
                            min="0.0"
                            max="2.0"
                            defaultValue={isNew ? 0.7 : model.temperature}
                            required
                        />
                        <small>0.0 = concise/factual → 2.0 = creative/dynamic</small>
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="model-tokens">Max Tokens *</label>
                        <input
                            id="model-tokens"
                            type="number"
                            name="maxTokens"
                            min="1"
                            defaultValue={isNew ? 2048 : model.maxTokens}
                            required
                        />
                    </div>
                    <div className="ai-drawer__field">
                        <label className="ai-drawer__checkbox">
                            <input
                                type="checkbox"
                                name="isActive"
                                value="true"
                                defaultChecked={isNew ? false : model.isActive}
                            />
                            <span>Activate Globally</span>
                        </label>
                    </div>
                    <div className="ai-drawer__actions">
                        <button type="submit" className="ai-drawer__submit" disabled={submitting}>
                            {submitting ? 'Saving...' : 'Save Config'}
                        </button>
                        <button type="button" className="ai-drawer__cancel" onClick={onClose}>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
