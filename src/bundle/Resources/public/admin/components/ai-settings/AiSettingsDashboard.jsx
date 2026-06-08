import React, { useState, useEffect } from 'react';
import ActiveBanner from './ActiveBanner.jsx';
import ProviderCard from './ProviderCard.jsx';
import ProviderDrawer from './ProviderDrawer.jsx';
import ModelDrawer from './ModelDrawer.jsx';
import ConfirmModal from './ConfirmModal.jsx';
import UsagePanel from './UsagePanel.jsx';
import EmptyState from './EmptyState.jsx';
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
    const [activeTab, setActiveTab] = useState('providers');

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

    // Search matches:
    //   - Provider name / identifier / siteaccess
    //   - Model name / identifier belonging to that provider
    // Providers with no match (and no matching model) are hidden.
    // Providers with a matching model but not matching the search
    // themselves are auto-expanded so the match is visible.
    const query = searchQuery.trim().toLowerCase();
    const matchingModelIdsByProvider = new Map();
    if (query) {
        for (const m of data.models) {
            const haystack = `${m.name} ${m.identifier}`.toLowerCase();
            if (haystack.includes(query)) {
                if (!matchingModelIdsByProvider.has(m.providerId)) {
                    matchingModelIdsByProvider.set(m.providerId, []);
                }
                matchingModelIdsByProvider.get(m.providerId).push(m.id);
            }
        }
    }
    const totalMatchingModels = Array.from(matchingModelIdsByProvider.values())
        .reduce((sum, arr) => sum + arr.length, 0);

    const filteredProviders = query
        ? data.providers.filter((p) => {
            const providerHaystack = `${p.name} ${p.identifier} ${p.siteaccess || 'global'}`.toLowerCase();
            return providerHaystack.includes(query) || matchingModelIdsByProvider.has(p.id);
        })
        : data.providers;

    // Auto-expand any provider card whose model matched the search
    // (so the user actually sees the hit). Additive with the user's
    // manual expansion state.
    useEffect(() => {
        if (!query || matchingModelIdsByProvider.size === 0) return;
        setExpandedIds((prev) => {
            const next = new Set(prev);
            for (const pid of matchingModelIdsByProvider.keys()) {
                next.add(pid);
            }
            return next;
        });
    }, [query, matchingModelIdsByProvider]);

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

            <div className="ai-tabs" role="tablist" aria-label="AI settings views">
                <button
                    role="tab"
                    type="button"
                    aria-selected={activeTab === 'providers'}
                    className={`ai-tabs__tab ${activeTab === 'providers' ? 'ai-tabs__tab--active' : ''}`}
                    onClick={() => setActiveTab('providers')}
                >
                    Providers
                </button>
                <button
                    role="tab"
                    type="button"
                    aria-selected={activeTab === 'usage'}
                    className={`ai-tabs__tab ${activeTab === 'usage' ? 'ai-tabs__tab--active' : ''}`}
                    onClick={() => setActiveTab('usage')}
                >
                    Usage
                </button>
            </div>

            {activeTab === 'providers' ? (
                <>
                    <div className="ai-action-bar">
                        <div className="ai-action-bar__search">
                            <svg className="ibexa-icon ibexa-icon--small ai-action-bar__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input
                                type="text"
                                className="ibexa-input ibexa-input--text form-control ai-action-bar__input"
                                placeholder="Search providers and models..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                aria-label="Search providers and models"
                            />
                        </div>
                        <button type="button" className="ibexa-btn ibexa-btn--primary" onClick={() => setEditingProvider('new')}>
                            + Add Provider
                        </button>
                    </div>

                    {filteredProviders.length === 0 ? (
                        query ? (
                            <EmptyState
                                icon="🔍"
                                title="No matches"
                                description={`No provider or model matches "${searchQuery}".`}
                                ctaLabel="Clear search"
                                ctaVariant="tertiary"
                                onCta={() => setSearchQuery('')}
                            />
                        ) : (
                            <EmptyState
                                icon="🧠"
                                title="No providers configured"
                                description="Add your first AI provider to start using AI-assisted content editing."
                                ctaLabel="+ Add First Provider"
                                ctaVariant="primary"
                                onCta={() => setEditingProvider('new')}
                            />
                        )
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
                </>
            ) : (
                <UsagePanel />
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

