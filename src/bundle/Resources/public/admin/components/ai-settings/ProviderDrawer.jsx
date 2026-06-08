import React from 'react';
import { PROVIDER_TYPES } from './constants.js';
import { CloseIcon } from './icons.jsx';

export default function ProviderDrawer({ provider, siteaccesses = [], onClose, onSave, submitting }) {
    return (
        <div className="ai-drawer-overlay" onClick={onClose}>
            <div className="ibexa-side-panel ai-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Provider configuration">
                <div className="ibexa-side-panel__header">
                    <h4 className="ai-drawer__title">{provider === 'new' ? 'New Provider' : 'Edit Provider'}</h4>
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
                            <label className="ibexa-label" htmlFor="provider-name">Display Name *</label>
                            <input
                                id="provider-name"
                                className="ibexa-input ibexa-input--text form-control"
                                type="text"
                                name="name"
                                defaultValue={provider !== 'new' ? provider.name : ''}
                                required
                                placeholder="e.g. OpenAI Production"
                            />
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="provider-type">Provider API Type *</label>
                            <select
                                id="provider-type"
                                className="ibexa-input form-control"
                                name="identifier"
                                defaultValue={provider !== 'new' ? provider.identifier : 'openai'}
                            >
                                {PROVIDER_TYPES.map(t => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="provider-siteaccess">Siteaccess Scope</label>
                            <select
                                id="provider-siteaccess"
                                className="ibexa-input form-control"
                                name="siteaccess"
                                defaultValue={provider !== 'new' ? (provider.siteaccess || '') : ''}
                            >
                                <option value="">All siteaccesses (global)</option>
                                {siteaccesses.map(sa => (
                                    <option key={sa} value={sa}>{sa}</option>
                                ))}
                            </select>
                            <small className="ai-drawer__hint">Restrict this provider to a specific siteaccess, or leave as global.</small>
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="provider-key">API Key</label>
                            <input
                                id="provider-key"
                                className="ibexa-input ibexa-input--text form-control"
                                type="password"
                                name="apiKey"
                                defaultValue={provider !== 'new' ? provider.apiKey : ''}
                                placeholder={provider !== 'new' && provider.apiKey ? '••••••••' : 'Enter API Key'}
                            />
                            <small className="ai-drawer__hint">Leave empty for env-based keys or local providers.</small>
                        </div>
                        <div className="ai-drawer__field">
                            <label className="ibexa-label" htmlFor="provider-url">Endpoint URL</label>
                            <input
                                id="provider-url"
                                className="ibexa-input ibexa-input--text form-control"
                                type="url"
                                name="apiUrl"
                                defaultValue={provider !== 'new' && provider.apiUrl ? provider.apiUrl : ''}
                                placeholder="e.g. http://localhost:11434/v1"
                            />
                            <small className="ai-drawer__hint">Optional. Custom endpoint for self-hosted or proxy setups.</small>
                        </div>
                        <div className="ai-drawer__field">
                            <label className="form-check-inline ai-drawer__checkbox-row">
                                <input
                                    className="ibexa-input ibexa-input--checkbox"
                                    type="checkbox"
                                    name="isActive"
                                    value="true"
                                    defaultChecked={provider !== 'new' ? provider.isActive : false}
                                />
                                <span className="ibexa-label ibexa-label--checkbox-radio">Activate Immediately</span>
                            </label>
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
