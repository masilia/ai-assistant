import React from 'react';

export default function ActiveBanner({ providers, models, activeProviderId, activeModelId }) {
    const activeProvider = providers.find(p => p.id === activeProviderId);
    const activeModel = models.find(m => m.id === activeModelId);

    return (
        <div className="ai-banner" role="status" aria-label="Active AI engine status">
            <div>
                <span className="ai-banner__label">Currently Active LLM Engine</span>
                <h4 className="ai-banner__title">
                    {activeProvider ? activeProvider.name : 'None'}
                    <span className="ai-banner__divider">/</span>
                    {activeModel ? activeModel.name : 'No Active Model'}
                </h4>
            </div>
            <div className={`ai-banner__status ${activeProvider ? 'ai-banner__status--online' : ''}`}>
                <span className={`ai-banner__dot ${activeProvider ? 'ai-banner__dot--active' : ''}`} />
                <span>{activeProvider ? 'Online' : 'Offline'}</span>
            </div>
        </div>
    );
}
