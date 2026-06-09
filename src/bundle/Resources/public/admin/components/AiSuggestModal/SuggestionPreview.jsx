import React, { useMemo } from 'react';

/**
 * Suggestion preview panel: shows the streamed result and the Apply button.
 * For ezrichtext fields, renders HTML; for ezmatrix fields, renders an
 * inline table mirroring the source matrix's columns; otherwise renders
 * pre-formatted text.
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
