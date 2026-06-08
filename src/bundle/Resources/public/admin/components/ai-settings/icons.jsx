/**
 * Shared SVG icons used across the AI Assistant frontend.
 *
 * These are inline-SVG Lucide-style icons (the same paths Lucide
 * uses, MIT-licensed, no external dependency required). The package
 * avoids a hard dep on `lucide-react` so hosts with no JS build
 * pipeline (or a strict bundle-size budget) can still use it.
 *
 * Convention:
 *   - All icons accept a `size` prop (number) and a `className`
 *     passthrough. Default size = 20.
 *   - All icons are aria-hidden by default (decorative). Wrap in a
 *     <button> with an aria-label if the icon conveys the only
 *     meaning of the control.
 *   - stroke="currentColor" so CSS color cascades work.
 *
 * Adding a new icon: add a new named export here. Keep the prop
 * signature consistent (size + className) so future usage is
 * uniform.
 */
import React from 'react';

const DEFAULT_STROKE_WIDTH = 2;

function svgProps(size, className, strokeWidth = DEFAULT_STROKE_WIDTH) {
    return {
        className: `ibexa-icon ${className ?? ''}`.trim(),
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        width: size ?? 20,
        height: size ?? 20,
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': 'true',
        focusable: 'false',
    };
}

// ─── Quick action icons (8) ───────────────────────────────────

/** Wand2 — Improve. The 'AI assist' canonical icon in Lucide. */
export function WandIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M15 4V2" />
            <path d="M15 16v-2" />
            <path d="M8 9h2" />
            <path d="M20 9h2" />
            <path d="M17.8 11.8 19 13" />
            <path d="M15 9h0" />
            <path d="M17.8 6.2 19 5" />
            <path d="M3 21l9-9" />
            <path d="M12.2 6.2 11 5" />
        </svg>
    );
}

/** Minimize2 — Shorten. Two arrows pointing toward each other. */
export function MinimizeIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <polyline points="4 14 10 14 10 20" />
            <polyline points="20 10 14 10 14 4" />
            <line x1="14" y1="10" x2="21" y2="3" />
            <line x1="3" y1="21" x2="10" y2="14" />
        </svg>
    );
}

/** Maximize2 — Lengthen. Two arrows pointing away from each other. */
export function MaximizeIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <polyline points="15 3 21 3 21 9" />
            <polyline points="9 21 3 21 3 15" />
            <line x1="21" y1="3" x2="14" y2="10" />
            <line x1="3" y1="21" x2="10" y2="14" />
        </svg>
    );
}

/** SpellCheck2 — Fix Grammar. ABC with a checkmark. */
export function SpellCheckIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="m2 22 4-4 4 4 4-4 4 4 4-4" />
            <path d="m6 18 4-4 4 4 4-4 4 4" />
            <path d="M14 6h4v4" />
            <path d="M10 6H6v4" />
            <path d="M10 6V2" />
            <path d="M14 6V2" />
            <path d="m22 13-2 2-1-1" />
        </svg>
    );
}

/** Briefcase — Formal. Standard briefcase. */
export function BriefcaseIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
        </svg>
    );
}

/** Smile — Casual. The 'friendly' icon. */
export function SmileIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <circle cx="12" cy="12" r="10" />
            <path d="M8 14s1.5 2 4 2 4-2 4-2" />
            <line x1="9" y1="9" x2="9.01" y2="9" />
            <line x1="15" y1="9" x2="15.01" y2="9" />
        </svg>
    );
}

/** FileText — Summarize. Document with a summary line. */
export function FileTextIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
            <polyline points="10 9 9 9 8 9" />
        </svg>
    );
}

/** Languages — Translate. Globe. */
export function LanguagesIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="m5 8 6 6" />
            <path d="m4 14 6-6 3-3" />
            <path d="M2 5h12" />
            <path d="M7 2h1" />
            <path d="m22 22-5-10-5 10" />
            <path d="M14 18h6" />
        </svg>
    );
}

// ─── Empty state icons ───────────────────────────────────────

/** Brain — 'AI engine / configuration' canonical icon. */
export function BrainIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z" />
            <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z" />
        </svg>
    );
}

/** Bot — 'no models / AI agent' icon. */
export function BotIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M12 8V4H8" />
            <rect x="2" y="8" width="20" height="12" rx="2" ry="2" />
            <path d="M2 14h20" />
            <path d="M6 8v0" />
            <path d="M18 8v0" />
            <path d="M12 8v0" />
        </svg>
    );
}

/** Search — empty state for 'no matches'. */
export function SearchXIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="m21 21-3.5-3.5" />
            <circle cx="11" cy="11" r="6" />
            <line x1="8" y1="8" x2="14" y2="14" />
            <line x1="14" y1="8" x2="8" y2="14" />
        </svg>
    );
}

// ─── UI icons (previously inline SVGs) ──────────────────────

/** Sparkles / star — used as the AI trigger / generate icon. */
export function SparklesIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/>
        </svg>
    );
}

/** ChevronDown — used in the collapsible provider card header. */
export function ChevronIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <polyline points="6 9 12 15 18 9" />
        </svg>
    );
}

/** X — close (modals, drawers). */
export function CloseIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    );
}

/** Search — used in the action bar. */
export function SearchIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
    );
}

/** Pencil — used as the edit-action icon on cards. */
export function EditIcon({ size, className }) {
    return (
        <svg viewBox="0 0 32 32" className={`ibexa-icon ${className ?? ''}`.trim()} width={20} height={20} aria-hidden="true">
            <path fill="currentColor" d="M27.253 7.857l-1.36 2.183c-0.239 0.38-0.657 0.629-1.132 0.629-0.241 0-0.466-0.064-0.661-0.175l0.007 0.003-9.087-5.117c-0.408-0.233-0.678-0.666-0.678-1.161 0-0.261 0.075-0.505 0.205-0.711l-0.003 0.006 1.363-2.18c0.533-0.808 1.436-1.334 2.463-1.334 0.532 0 1.031 0.141 1.462 0.388l-0.014-0.008 6.393 3.6c0.671 0.379 1.166 1.004 1.365 1.75l0.005 0.020c0.056 0.204 0.088 0.437 0.088 0.679 0 0.53-0.154 1.024-0.421 1.439l0.006-0.011zM6.52 31.91l8.197-3.18c0.296-0.117 0.535-0.329 0.683-0.596l0.003-0.007 7.42-13.58c0.13-0.202 0.207-0.449 0.207-0.714 0-0.736-0.597-1.333-1.333-1.333-0.531 0-0.989 0.31-1.204 0.759l-0.003 0.008-7.193 13.153-6 2.333-0.297-6.213 7.257-13.25c0.13-0.202 0.207-0.449 0.207-0.714 0-0.736-0.597-1.333-1.333-1.333-0.531 0-0.989 0.31-1.204 0.759l-0.003 0.008-7.427 13.577c-0.104 0.185-0.165 0.406-0.165 0.642 0 0.020 0 0.041 0.001 0.061l-0-0.003 0.37 8.44c0.032 0.711 0.616 1.275 1.332 1.275 0.174 0 0.341-0.033 0.494-0.094l-0.009 0.003z" />
        </svg>
    );
}

/** Trash — used as the delete-action icon on cards. */
export function DeleteIcon({ size, className }) {
    return (
        <svg viewBox="0 0 32 32" className={`ibexa-icon ${className ?? ''}`.trim()} width={20} height={20} aria-hidden="true">
            <path fill="currentColor" d="M29.333 5.333h-5.333v-2.64c0-1.537-0.917-2.693-2.133-2.693h-11.753c-1.207 0-2.113 1.143-2.113 2.667v2.667h-5.333c-0.736 0-1.333 0.597-1.333 1.333s0.597 1.333 1.333 1.333h2v21.15c0.013 1.577 1.295 2.85 2.873 2.85h16.949c1.546 0 2.8-1.246 2.813-2.789v-21.211h2c0.736 0 1.333-0.597 1.333-1.333s-0.597-1.333-1.333-1.333zM10.667 2.667h10.667v2.667h-10.667zM24.667 29.21c-0.011 0.071-0.072 0.124-0.145 0.124h-16.95c-0.107 0-0.195-0.080-0.208-0.183v-21.15h17.333zM16 26.667c-0.736 0-1.333-0.597-1.333-1.333v-13.333c0-0.736 0.597-1.333 1.333-1.333s1.333 0.597 1.333 1.333v13.333c0 0.736-0.597 1.333-1.333 1.333zM13 22.667v-8c0-0.736-0.597-1.333-1.333-1.333s-1.333 0.597-1.333 1.333v8c0 0.736 0.597 1.333 1.333 1.333s1.333-0.597 1.333-1.333zM21.667 22.667v-8c0-0.736-0.597-1.333-1.333-1.333s-1.333 1.333-1.333 1.333v8c0 0.736 0.597 1.333 1.333 1.333s1.333-0.597 1.333-1.333z" />
        </svg>
    );
}

/** RefreshCw — used in the banner's refresh button. */
export function RefreshIcon({ size, className }) {
    return (
        <svg {...svgProps(size, className)}>
            <polyline points="23 4 23 10 17 10" />
            <polyline points="1 20 1 14 7 14" />
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
        </svg>
    );
}
