import React from 'react';
import { PROVIDER_TYPES } from './constants.js';

export default function ProviderDrawer({ provider, siteaccesses = [], onClose, onSave, submitting }) {
    return (
        <div className="ai-drawer-overlay" onClick={onClose}>
            <div className="ai-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Provider configuration">
                <div className="ai-drawer__header">
                    <h4>{provider === 'new' ? 'New Provider' : 'Edit Provider'}</h4>
                    <button type="button" className="ai-drawer__close" onClick={onClose} aria-label="Close">&times;</button>
                </div>
                <form className="ai-drawer__form" onSubmit={onSave}>
                    <div className="ai-drawer__field">
                        <label htmlFor="provider-name">Display Name *</label>
                        <input
                            id="provider-name"
                            type="text"
                            name="name"
                            defaultValue={provider !== 'new' ? provider.name : ''}
                            required
                            placeholder="e.g. OpenAI Production"
                        />
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="provider-type">Provider API Type *</label>
                        <select
                            id="provider-type"
                            name="identifier"
                            defaultValue={provider !== 'new' ? provider.identifier : 'openai'}
                        >
                            {PROVIDER_TYPES.map(t => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="provider-siteaccess">Siteaccess Scope</label>
                        <select
                            id="provider-siteaccess"
                            name="siteaccess"
                            defaultValue={provider !== 'new' ? (provider.siteaccess || '') : ''}
                        >
                            <option value="">All siteaccesses (global)</option>
                            {siteaccesses.map(sa => (
                                <option key={sa} value={sa}>{sa}</option>
                            ))}
                        </select>
                        <small>Restrict this provider to a specific siteaccess, or leave as global.</small>
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="provider-key">API Key</label>
                        <input
                            id="provider-key"
                            type="password"
                            name="apiKey"
                            defaultValue={provider !== 'new' ? provider.apiKey : ''}
                            placeholder={provider !== 'new' && provider.apiKey ? '••••••••' : 'Enter API Key'}
                        />
                        <small>Leave empty for env-based keys or local providers.</small>
                    </div>
                    <div className="ai-drawer__field">
                        <label htmlFor="provider-url">Endpoint URL</label>
                        <input
                            id="provider-url"
                            type="url"
                            name="apiUrl"
                            defaultValue={provider !== 'new' && provider.apiUrl ? provider.apiUrl : ''}
                            placeholder="e.g. http://localhost:11434/v1"
                        />
                        <small>Optional. Custom endpoint for self-hosted or proxy setups.</small>
                    </div>
                    <div className="ai-drawer__field">
                        <label className="ai-drawer__checkbox">
                            <input
                                type="checkbox"
                                name="isActive"
                                value="true"
                                defaultChecked={provider !== 'new' ? provider.isActive : false}
                            />
                            <span>Activate Immediately</span>
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
