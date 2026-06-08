import React from 'react';
import { QUICK_ACTIONS } from '../ai-settings/constants.js';

/**
 * Quick action chips row. Each chip pre-fills the prompt or triggers
 * a translation sub-flow. Selected chip is highlighted.
 */
export default function QuickActions({ selectedId, onSelect, disabled, isTranslationDisabled }) {
    return (
        <div className="ai-suggest-modal__quick-actions">
            <span className="ai-suggest-modal__quick-actions-label">Quick:</span>
            <div className="ai-suggest-modal__quick-actions-list">
                {QUICK_ACTIONS.map((action) => {
                    const isDisabled = disabled || (isTranslationDisabled && action.isTranslation);
                    const isActive = selectedId === action.id;
                    const title = isTranslationDisabled && action.isTranslation
                        ? 'Translation is not supported for SEO Metas'
                        : action.promptTemplate;

                    return (
                        <button
                            key={action.id}
                            type="button"
                            className={`ai-suggest-modal__quick-action ${isActive ? 'ai-suggest-modal__quick-action--active' : ''}`}
                            onClick={() => onSelect(action)}
                            disabled={isDisabled}
                            title={title}
                        >
                            <span className="ai-suggest-modal__quick-action-icon">{action.icon}</span>
                            <span className="ai-suggest-modal__quick-action-label">{action.label}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
