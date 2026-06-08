import React, { useRef } from 'react';
import { QUICK_ACTIONS } from '../../ai-settings/constants.js';

/**
 * Quick action chips row. Each chip pre-fills the prompt or triggers
 * a translation sub-flow. Selected chip is highlighted.
 *
 * Keyboard nav: Left/Right arrow keys move focus between chips
 * (roving tabindex). The currently-focused chip has tabindex=0;
 * the rest have tabindex=-1 so Tab moves out of the row entirely.
 * Home/End jump to first/last. The arrow key handler also calls
 * onSelect so keyboard users get the same one-click behavior as
 * mouse users.
 */
export default function QuickActions({
    selectedId,
    onSelect,
    disabled,
    isTranslationDisabled,
}) {
    const listRef = useRef(null);

    const handleKeyDown = (e) => {
        if (disabled) return;
        const chips = Array.from(listRef.current?.querySelectorAll('[data-quick-action]') ?? []);
        const currentIndex = chips.findIndex((el) => el === document.activeElement);
        if (currentIndex === -1) return;

        let nextIndex = currentIndex;
        switch (e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                nextIndex = (currentIndex + 1) % chips.length;
                e.preventDefault();
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                nextIndex = (currentIndex - 1 + chips.length) % chips.length;
                e.preventDefault();
                break;
            case 'Home':
                nextIndex = 0;
                e.preventDefault();
                break;
            case 'End':
                nextIndex = chips.length - 1;
                e.preventDefault();
                break;
            default:
                return;
        }

        chips[nextIndex].focus();
    };

    return (
        <div className="ai-suggest-modal__quick-actions">
            <span className="ai-suggest-modal__quick-actions-label" id="ai-quick-actions-label">
                Quick:
            </span>
            <div
                className="ai-suggest-modal__quick-actions-list"
                role="toolbar"
                aria-labelledby="ai-quick-actions-label"
                onKeyDown={handleKeyDown}
                ref={listRef}
            >
                {QUICK_ACTIONS.map((action, index) => {
                    const isDisabled = disabled || (isTranslationDisabled && action.isTranslation);
                    const isActive = selectedId === action.id;
                    const title = isTranslationDisabled && action.isTranslation
                        ? 'Translation is not supported for SEO Metas'
                        : action.promptTemplate;

                    return (
                        <button
                            key={action.id}
                            type="button"
                            data-quick-action
                            tabIndex={index === 0 ? 0 : -1}
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
