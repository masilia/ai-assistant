import React from 'react';
import { getProviderLabel } from './constants.js';

/**
 * Siteaccess × Provider visualization matrix.
 * Rows = siteaccesses, columns = providers.
 * Cells show a colored dot: green = assigned with chat model,
 * amber = assigned but no model, empty = not assigned.
 */
export default function SiteaccessMatrix({ providers = [], siteaccesses = [], currentSiteaccess = '' }) {
    if (providers.length === 0 || siteaccesses.length === 0) return null;

    return (
        <div className="ai-matrix">
            <div className="ai-matrix__header">
                <div className="ai-matrix__corner">Siteaccess</div>
                {providers.map(p => (
                    <div key={p.id} className="ai-matrix__col-header" title={p.name}>
                        <span className="ai-matrix__col-name">{p.name}</span>
                        <span className="ai-matrix__col-type">{getProviderLabel(p.identifier)}</span>
                    </div>
                ))}
            </div>
            {siteaccesses.map(sa => (
                <div key={sa} className={`ai-matrix__row ${sa === currentSiteaccess ? 'ai-matrix__row--current' : ''}`}>
                    <div className="ai-matrix__row-label">
                        {sa}
                        {sa === currentSiteaccess && <span className="ai-matrix__current-tag">current</span>}
                    </div>
                    {providers.map(p => {
                        const assigned = p.siteaccesses.includes(sa);
                        const hasChatModel = assigned && p.activeChatModelId;
                        return (
                            <div key={p.id} className="ai-matrix__cell">
                                {assigned && (
                                    <span
                                        className={`ai-matrix__dot ${hasChatModel ? 'ai-matrix__dot--ok' : 'ai-matrix__dot--warn'}`}
                                        title={hasChatModel ? 'Assigned with chat model' : 'Assigned, no chat model'}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
