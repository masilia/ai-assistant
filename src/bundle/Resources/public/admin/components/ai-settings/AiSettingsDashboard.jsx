import React, { useState, useEffect } from 'react';
import ActiveBanner from './ActiveBanner.jsx';
import ProviderCard from './ProviderCard.jsx';
import ProviderDrawer from './ProviderDrawer.jsx';
import ModelDrawer from './ModelDrawer.jsx';
import ConfirmModal from './ConfirmModal.jsx';
import { useAiSettings } from './useAiSettings.js';

export default function AiSettingsDashboard() {
    const {
        data, loading, submitting, testingId, testResults,
        saveProvider, deleteProvider, activateProvider, testProvider,
        saveModel, deleteModel, activateModel,
    } = useAiSettings();

    const [searchQuery, setSearchQuery] = useState('');
    const [expandedIds, setExpandedIds] = useState(new Set());
    const [editingProvider, setEditingProvider] = useState(null);
    const [editingModel, setEditingModel] = useState(null);
    const [modelPreselectedProviderId, setModelPreselectedProviderId] = useState(null);
    const [confirmAction, setConfirmAction] = useState(null);

    // Auto-expand the active provider on first successful load
    useEffect(() => {
        if (data.activeProviderId) {
            setExpandedIds(prev => new Set(prev).add(data.activeProviderId));
        }
    }, [data.activeProviderId]);

    // ── UI handlers ────────────────────────────────────────────────────────
    const handleSaveProvider = async (e) => {
        const saved = await saveProvider(e, editingProvider);
        if (saved) setEditingProvider(null);
    };

    const handleDeleteProvider = (id) => {
        setConfirmAction({
            title: 'Delete Provider',
            confirmLabel: 'Delete',
            description: 'This will permanently remove this provider and all of its model configurations. This action cannot be undone.',
            onConfirm: async () => { setConfirmAction(null); await deleteProvider(id); },
        });
    };

    const handleSaveModel = async (e) => {
        const saved = await saveModel(e, editingModel);
        if (saved) { setEditingModel(null); setModelPreselectedProviderId(null); }
    };

    const handleDeleteModel = (id) => {
        setConfirmAction({
            title: 'Delete Model',
            confirmLabel: 'Delete',
            description: 'This will permanently remove this model configuration. This action cannot be undone.',
            onConfirm: async () => { setConfirmAction(null); await deleteModel(id); },
        });
    };

    const openAddModel = (providerId) => {
        setModelPreselectedProviderId(providerId);
        setEditingModel('new');
    };

    const toggleExpand = (id) => {
        setExpandedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id); else next.add(id);
            return next;
        });
    };

    const filteredProviders = data.providers.filter(p =>
        p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.identifier.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (p.siteaccess || 'global').toLowerCase().includes(searchQuery.toLowerCase())
    );

    // ── Render ─────────────────────────────────────────────────────────────
    if (loading) {
        return (
            <div className="ai-dashboard">
                <div className="ai-loader" aria-label="Loading AI settings">
                    <div className="ai-loader__spinner" />
                </div>
            </div>
        );
    }

    return (
        <div className="ai-dashboard">
            <ActiveBanner
                providers={data.providers}
                models={data.models}
                activeProviderId={data.activeProviderId}
                activeModelId={data.activeModelId}
                currentSiteaccess={data.currentSiteaccess}
            />

            <div className="ai-action-bar">
                <div className="ai-action-bar__search">
                    <svg className="ibexa-icon ibexa-icon--small ai-action-bar__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input
                        type="text"
                        className="ibexa-input ibexa-input--text form-control ai-action-bar__input"
                        placeholder="Search providers..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        aria-label="Search providers"
                    />
                </div>
                <button type="button" className="ibexa-btn ibexa-btn--primary" onClick={() => setEditingProvider('new')}>
                    + Add Provider
                </button>
            </div>

            {filteredProviders.length === 0 ? (
                <div className="ai-empty-state">
                    <div className="ai-empty-state__icon">🧠</div>
                    <p className="ai-empty-state__title">No providers configured</p>
                    <p className="ai-empty-state__desc">Add your first AI provider to start using AI-assisted content editing.</p>
                    <button type="button" className="ibexa-btn ibexa-btn--primary" onClick={() => setEditingProvider('new')}>
                        + Add First Provider
                    </button>
                </div>
            ) : (
                <div className="ai-providers-stack">
                    {filteredProviders.map(p => (
                        <ProviderCard
                            key={p.id}
                            provider={p}
                            models={data.models}
                            currentSiteaccess={data.currentSiteaccess}
                            isExpanded={expandedIds.has(p.id)}
                            onToggleExpand={() => toggleExpand(p.id)}
                            onActivateProvider={activateProvider}
                            onEditProvider={setEditingProvider}
                            onDeleteProvider={handleDeleteProvider}
                            onTestProvider={testProvider}
                            testingId={testingId}
                            testResult={testResults[p.id] || null}
                            onActivateModel={activateModel}
                            onEditModel={(m) => { setModelPreselectedProviderId(null); setEditingModel(m); }}
                            onDeleteModel={handleDeleteModel}
                            onAddModel={openAddModel}
                        />
                    ))}
                </div>
            )}

            {editingProvider && (
                <ProviderDrawer
                    provider={editingProvider}
                    siteaccesses={data.siteaccesses || []}
                    onClose={() => setEditingProvider(null)}
                    onSave={handleSaveProvider}
                    submitting={submitting}
                />
            )}

            {editingModel && (
                <ModelDrawer
                    model={editingModel}
                    providers={data.providers}
                    preselectedProviderId={modelPreselectedProviderId}
                    onClose={() => { setEditingModel(null); setModelPreselectedProviderId(null); }}
                    onSave={handleSaveModel}
                    submitting={submitting}
                />
            )}

            {confirmAction && (
                <ConfirmModal
                    title={confirmAction.title}
                    description={confirmAction.description}
                    confirmLabel={confirmAction.confirmLabel}
                    onConfirm={confirmAction.onConfirm}
                    onCancel={() => setConfirmAction(null)}
                />
            )}
        </div>
    );
}

