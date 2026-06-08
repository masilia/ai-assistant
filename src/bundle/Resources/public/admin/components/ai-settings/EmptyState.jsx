import React from 'react';

/**
 * EmptyState — shared empty-state block for the AI dashboard.
 *
 * Two call sites previously duplicated this markup with different
 * icons, different emoji, and different wording — the inconsistency
 * was visible in the no-providers vs no-matches vs no-models
 * states. This component centralises the structure (icon + title +
 * description + optional CTA) and uses a single CSS class
 * (.ai-empty-state) so the SCSS lives in one place.
 *
 * @param {object}  props
 * @param {string}  [props.icon]     Single emoji or short text rendered above the title.
 * @param {string}  props.title      Bold heading (e.g. 'No providers configured').
 * @param {string}  [props.description] One-line grey description below the title.
 * @param {string}  [props.ctaLabel] Optional CTA button label.
 * @param {() => void} [props.onCta] Optional CTA click handler.
 * @param {'primary'|'tertiary'} [props.ctaVariant='primary']
 *                                  CTA visual variant. 'tertiary' for utility actions
 *                                  (e.g. 'Clear search'), 'primary' for the main
 *                                  setup action (e.g. '+ Add First Provider').
 */
export default function EmptyState({
    icon = '🧠',
    title,
    description,
    ctaLabel,
    onCta,
    ctaVariant = 'primary',
}) {
    return (
        <div className="ai-empty-state">
            <div className="ai-empty-state__icon" aria-hidden="true">{icon}</div>
            <p className="ai-empty-state__title">{title}</p>
            {description && <p className="ai-empty-state__desc">{description}</p>}
            {ctaLabel && onCta && (
                <button
                    type="button"
                    className={`ibexa-btn ibexa-btn--${ctaVariant}`}
                    onClick={onCta}
                >
                    {ctaLabel}
                </button>
            )}
        </div>
    );
}
