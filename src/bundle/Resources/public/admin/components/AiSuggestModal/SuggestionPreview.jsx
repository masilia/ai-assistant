import React, { useMemo } from 'react';

/**
 * Suggestion preview panel: shows the streamed result and the Apply button.
 * For ezrichtext fields, renders HTML; for ezmatrix fields, renders an
 * inline table mirroring the source matrix's columns; for novaseometas
 * fields, renders a key-value table; otherwise renders pre-formatted text.
 */
export default function SuggestionPreview({ text, fieldType, onApply }) {
    if (!text) return null;

    return (
        <div className="ai-suggest-modal__preview-section">
            <div className="ai-suggest-modal__preview-header">
                <span>Preview</span>
                <button
                    className="ibexa-btn ibexa-btn--filled-info ibexa-btn--small"
                    onClick={onApply}
                    type="button"
                >
                    Apply
                    <kbd className="ai-suggest-modal__kbd">⌘↵</kbd>
                </button>
            </div>
            {/*
              aria-live="polite" announces streamed tokens to assistive tech
              without interrupting the current announcement. aria-atomic="false"
              so only newly appended nodes are read (not the whole preview).
            */}
            <div
                className="ai-suggest-modal__preview"
                aria-live="polite"
                aria-atomic="false"
                aria-label="AI suggestion preview"
            >
                {fieldType === 'ezrichtext' ? (
                    <div dangerouslySetInnerHTML={{ __html: text }} />
                ) : fieldType === 'ezmatrix' ? (
                    <MatrixPreview text={text} />
                ) : fieldType === 'novaseometas' ? (
                    <NovaSeoPreview text={text} />
                ) : (
                    <div className="ai-suggest-modal__preview-text">{text}</div>
                )}
            </div>
        </div>
    );
}

/**
 * Renders an AI-suggested matrix value as an inline <table>.
 *
 * Accepts both shapes that the AI may emit:
 *  - {"rows": [{"cells": {"col_a": "v", "col_b": "v"}}, ...]}  (preferred)
 *  - {"rows": [{"cells": ["v", "v"]}, ...]}                       (positional fallback)
 *
 * If `headers` is provided (object {colId: "Column Name"}), the table has
 * a header row. Otherwise it renders without one.
 */
function MatrixPreview({ text }) {
    const data = useMemo(() => {
        try {
            const parsed = JSON.parse(text);
            return (parsed && Array.isArray(parsed.rows)) ? parsed : null;
        } catch {
            return null;
        }
    }, [text]);

    if (!data) {
        return <div className="ai-suggest-modal__preview-text">{text}</div>;
    }

    const columnIds = collectColumnIds(data.rows);
    const headerFor = (id) => data.headers?.[id] ?? id;

    return (
        <div className="ai-suggest-modal__matrix-preview">
            <table className="ai-suggest-modal__matrix-table">
                {columnIds.length > 0 && (
                    <thead>
                        <tr>
                            {columnIds.map((id) => (
                                <th key={id} scope="col">{headerFor(id)}</th>
                            ))}
                        </tr>
                    </thead>
                )}
                <tbody>
                    {data.rows.map((row, i) => (
                        <tr key={i}>
                            {columnIds.map((colId) => {
                                const value = cellValue(row?.cells, colId);
                                return (
                                    <td key={colId}>{value || <span className="ai-suggest-modal__matrix-cell--empty">∅</span>}</td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function collectColumnIds(rows) {
    const seen = new Set();
    const order = [];
    for (const row of rows) {
        if (!row || !row.cells) continue;
        if (Array.isArray(row.cells)) {
            for (let i = 0; i < row.cells.length; i++) {
                const key = String(i);
                if (!seen.has(key)) { seen.add(key); order.push(key); }
            }
        } else if (typeof row.cells === 'object') {
            for (const key of Object.keys(row.cells)) {
                if (!seen.has(key)) { seen.add(key); order.push(key); }
            }
        }
    }
    return order;
}

function cellValue(cells, colId) {
    if (!cells) return '';
    if (Array.isArray(cells)) {
        const idx = Number(colId);
        return cells[idx] ?? '';
    }
    if (typeof cells === 'object') {
        return cells[colId] ?? '';
    }
    return '';
}

/**
 * Human-readable labels for well-known SEO meta keys.
 *
 * @type {Record<string, string>}
 */
const SEO_KEY_LABELS = {
    title: 'Title',
    description: 'Description',
    keywords: 'Keywords',
    canonical: 'Canonical',
    type: 'Type',
    'og:title': 'OG Title',
    'og:description': 'OG Description',
    'og:image': 'OG Image',
    'twitter:title': 'Twitter Title',
    'twitter:description': 'Twitter Description',
    'twitter:image': 'Twitter Image',
};

/**
 * Renders an AI-suggested novaseometas value as a key-value table.
 *
 * Expects the AI to return a flat JSON object: {"title": "...", "description": "..."}.
 * If parsing fails, falls back to raw text.
 */
function NovaSeoPreview({ text }) {
    const data = useMemo(() => {
        try {
            const cleaned = text.replace(/^```(json)?/i, '').replace(/```$/, '').trim();
            const parsed = JSON.parse(cleaned);
            return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : null;
        } catch {
            return null;
        }
    }, [text]);

    if (!data) {
        return <div className="ai-suggest-modal__preview-text">{text}</div>;
    }

    const entries = Object.entries(data);
    if (entries.length === 0) {
        return <div className="ai-suggest-modal__preview-text">{text}</div>;
    }

    return (
        <div className="ai-suggest-modal__seo-preview">
            <table className="ai-suggest-modal__seo-table">
                <thead>
                    <tr>
                        <th scope="col">Meta Key</th>
                        <th scope="col">Value</th>
                    </tr>
                </thead>
                <tbody>
                    {entries.map(([key, val]) => (
                        <tr key={key}>
                            <td className="ai-suggest-modal__seo-key">
                                {SEO_KEY_LABELS[key] || key}
                                <span className="ai-suggest-modal__seo-key-id">{key}</span>
                            </td>
                            <td className="ai-suggest-modal__seo-value">
                                {val === null || val === '' ? (
                                    <span className="ai-suggest-modal__seo-cell--empty">(empty)</span>
                                ) : (
                                    String(val)
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
