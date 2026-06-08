/**
 * Shared SVG icons used across the AI Assistant frontend.
 *
 * Each component takes a `size` prop (default 'small-medium' to
 * match the existing inline usage). All icons are extracted from
 * inline SVGs that used to be copy-pasted between ProviderCard,
 * ModelCard, AiSuggestModal, etc.
 *
 * Adding a new icon: write a new named export here, then import
 * it from the consumer. Keep the prop signature consistent
 * (size + className passthrough) so future usage is uniform.
 */
import React from 'react';

const SIZES = {
    tiny:        16,
    'tiny-small': 18,
    small:        20,
    'small-medium': 24,
    medium:       32,
};

function svgProps(size, className) {
    const dim = SIZES[size] ?? SIZES['small-medium'];
    return {
        className: `ibexa-icon ${className ?? ''}`.trim(),
        viewBox: '0 0 24 24',
        width: dim,
        height: dim,
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 2,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': 'true',
    };
}

/** ✨ Sparkles / star — used as the AI trigger / generate icon. */
export function SparklesIcon({ size = 'small-medium', className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.4 5.7 21l2.3-7L2 9.4h7.6z" />
        </svg>
    );
}

/** ✏️ Pencil — used as the edit-action icon on cards. */
export function EditIcon({ size = 'small', className }) {
    return (
        <svg viewBox="0 0 32 32" className={`ibexa-icon ${className ?? ''}`.trim()} width={20} height={20} aria-hidden="true">
            <path fill="currentColor" d="M27.253 7.857l-1.36 2.183c-0.239 0.38-0.657 0.629-1.132 0.629-0.241 0-0.466-0.064-0.661-0.175l0.007 0.003-9.087-5.117c-0.408-0.233-0.678-0.666-0.678-1.161 0-0.261 0.075-0.505 0.205-0.711l-0.003 0.006 1.363-2.18c0.533-0.808 1.436-1.334 2.463-1.334 0.532 0 1.031 0.141 1.462 0.388l-0.014-0.008 6.393 3.6c0.671 0.379 1.166 1.004 1.365 1.75l0.005 0.020c0.056 0.204 0.088 0.437 0.088 0.679 0 0.53-0.154 1.024-0.421 1.439l0.006-0.011zM6.52 31.91l8.197-3.18c0.296-0.117 0.535-0.329 0.683-0.596l0.003-0.007 7.42-13.58c0.13-0.202 0.207-0.449 0.207-0.714 0-0.736-0.597-1.333-1.333-1.333-0.531 0-0.989 0.31-1.204 0.759l-0.003 0.008-7.193 13.153-6 2.333-0.297-6.213 7.257-13.25c0.13-0.202 0.207-0.449 0.207-0.714 0-0.736-0.597-1.333-1.333-1.333-0.531 0-0.989 0.31-1.204 0.759l-0.003 0.008-7.427 13.577c-0.104 0.185-0.165 0.406-0.165 0.642 0 0.020 0 0.041 0.001 0.061l-0-0.003 0.37 8.44c0.032 0.711 0.616 1.275 1.332 1.275 0.174 0 0.341-0.033 0.494-0.094l-0.009 0.003z" />
        </svg>
    );
}

/** 🗑️ Trash — used as the delete-action icon on cards. */
export function DeleteIcon({ size = 'small', className }) {
    return (
        <svg viewBox="0 0 32 32" className={`ibexa-icon ${className ?? ''}`.trim()} width={20} height={20} aria-hidden="true">
            <path fill="currentColor" d="M29.333 5.333h-5.333v-2.64c0-1.537-0.917-2.693-2.133-2.693h-11.753c-1.207 0-2.113 1.143-2.113 2.667v2.667h-5.333c-0.736 0-1.333 0.597-1.333 1.333s0.597 1.333 1.333 1.333h2v21.15c0.013 1.577 1.295 2.85 2.873 2.85h16.949c1.546 0 2.8-1.246 2.813-2.789v-21.211h2c0.736 0 1.333-0.597 1.333-1.333s-0.597-1.333-1.333-1.333zM10.667 2.667h10.667v2.667h-10.667zM24.667 29.21c-0.011 0.071-0.072 0.124-0.145 0.124h-16.95c-0.107 0-0.195-0.080-0.208-0.183v-21.15h17.333zM16 26.667c-0.736 0-1.333-0.597-1.333-1.333v-13.333c0-0.736 0.597-1.333 1.333-1.333s1.333 0.597 1.333 1.333v13.333c0 0.736-0.597 1.333-1.333 1.333zM13 22.667v-8c0-0.736-0.597-1.333-1.333-1.333s-1.333 0.597-1.333 1.333v8c0 0.736 0.597 1.333 1.333 1.333s1.333-0.597 1.333-1.333zM21.667 22.667v-8c0-0.736-0.597-1.333-1.333-1.333s-1.333 1.333-1.333 1.333v8c0 0.736 0.597 1.333 1.333 1.333s1.333-0.597 1.333-1.333z" />
        </svg>
    );
}

/** ❌ Close (X) — used on modal close buttons. */
export function CloseIcon({ size = 'small', className }) {
    return (
        <svg {...svgProps(size, className)}>
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    );
}

/** 🔍 Search — used in the action bar. */
export function SearchIcon({ size = 'small', className }) {
    return (
        <svg {...svgProps(size, className)}>
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
    );
}

/** ⌄ Chevron — used in the collapsible provider card header. */
export function ChevronIcon({ size = 'small', className }) {
    return (
        <svg {...svgProps(size, className)}>
            <polyline points="9 6 15 12 9 18" />
        </svg>
    );
}
