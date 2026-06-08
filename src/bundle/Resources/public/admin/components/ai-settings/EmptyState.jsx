import React from 'react';

/**
 * EmptyState — shared empty-state block for the AI dashboard.
 *
 * The `icon` prop is a React component (Lucide-style SVG from
 * ./icons.jsx), not a string. Callers should pass e.g.
 * <EmptyState icon={BrainIcon} ... />.
 *
 * Default size for the rendered icon is 48 (visible-but-secondary).
 * Pass `iconSize` to override.
 *
 * @param {object}  props
 * @param {React.ComponentType} [props.icon]   Lucide icon component.
 * @param {number}  [props.iconSize=48]      Rendered icon size in px.
 * @param {string}  props.title              Bold heading.
 * @param {string}  [props.description]      One-line grey description.
 * @param {string}  [props.ctaLabel]         Optional CTA button label.
 * @param {() => void} [props.onCta]         Optional CTA click handler.
 * @param {'primary'|'secondary'|'tertiary'} [props.ctaVariant='primary']
 */
export default function EmptyState({
    icon: IconComponent,
    iconSize = 48,
    title,
    description,
    ctaLabel,
    onCta,
    ctaVariant = 'primary',
}) {
    return (
        <div className="ai-empty-state">
            {IconComponent && (
                <div className="ai-empty-state__icon" aria-hidden="true">
                    <IconComponent size={iconSize} />
                </div>
            )}
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
