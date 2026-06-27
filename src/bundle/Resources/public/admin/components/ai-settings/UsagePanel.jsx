import React, { useEffect, useState } from 'react';
import { AI_ROUTES } from './api-routes.js';
import { cleanErrorMessage } from './constants.js';

const WINDOWS = [
    { key: '24h', label: 'Last 24 hours' },
    { key: '7d',  label: 'Last 7 days' },
    { key: '30d', label: 'Last 30 days' },
];

const PROVIDER_LABELS = {
    openai:    'OpenAI',
    anthropic: 'Anthropic',
    mistral:   'Mistral',
    ollama:    'Ollama',
    minimax:   'MiniMax',
};

const formatMs = (n) => (n ? `${n} ms` : '—');
const formatInt = (n) => (n ? n.toLocaleString() : '0');
const formatPct = (success, total) => (total > 0 ? `${Math.round((success / total) * 100)}%` : '—');

/**
 * Mini sparkline bar chart — renders up to 12 bars from a value array.
 * Pure SVG, no dependencies. Bars are normalized to the max value.
 */
function Sparkline({ values, width = 80, height = 20 }) {
    if (!values || values.length === 0) return null;
    const max = Math.max(...values, 1);
    const barWidth = width / values.length;
    return (
        <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} className="ai-usage__sparkline" aria-hidden="true">
            {values.map((v, i) => {
                const h = Math.max(1, (v / max) * height);
                return (
                    <rect
                        key={i}
                        x={i * barWidth + 0.5}
                        y={height - h}
                        width={Math.max(1, barWidth - 1)}
                        height={h}
                        rx={1}
                        className="ai-usage__spark-bar"
                    />
                );
            })}
        </svg>
    );
}

/**
 * UsagePanel — read-only AI telemetry view.
 *
 * Shows three time windows (24h / 7d / 30d) with totals and a
 * per-provider breakdown. No real-time updates — the user clicks
 * 'Refresh' to re-fetch.
 */
export default function UsagePanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [activeWindow, setActiveWindow] = useState('7d');

    const fetchData = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await fetch(AI_ROUTES.usage, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load usage data.');
            setData(await res.json());
        } catch (err) {
            setError(cleanErrorMessage(err.message));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchData(); }, []);

    if (loading && !data) {
        return (
            <div className="ai-skeleton-usage" aria-busy="true" aria-label="Loading AI usage">
                <div className="ai-skeleton-card ai-skeleton-card--usage">
                    <div className="ai-skeleton-card__line ai-skeleton-card__line--title" />
                    {[1, 2, 3, 4].map((n) => (
                        <div key={n} className="ai-skeleton-card__line ai-skeleton-card__line--row" />
                    ))}
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="ibexa-alert ibexa-alert--error">
                <div className="ibexa-alert__content">
                    <span className="ibexa-alert__title"><strong>Error:</strong> {error}</span>
                </div>
            </div>
        );
    }

    if (!data) return null;

    const window = data.windows[activeWindow] ?? data.windows['7d'];
    const totals = window.totals;

    return (
        <div className="ai-usage">
            <div className="ai-usage__toolbar">
                <div className="ai-usage__tabs" role="tablist" aria-label="Time window">
                    {WINDOWS.map((w) => (
                        <button
                            key={w.key}
                            role="tab"
                            type="button"
                            aria-selected={activeWindow === w.key}
                            className={`ai-usage__tab ${activeWindow === w.key ? 'ai-usage__tab--active' : ''}`}
                            onClick={() => setActiveWindow(w.key)}
                        >
                            {w.label}
                        </button>
                    ))}
                </div>
                <button
                    type="button"
                    className="ibexa-btn ibexa-btn--ghost ibexa-btn--small"
                    onClick={fetchData}
                    disabled={loading}
                >
                    {loading ? 'Refreshing…' : '↻ Refresh'}
                </button>
            </div>

            <div className="ai-usage__totals">
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Total requests</span>
                    <span className="ai-usage__stat-value">{formatInt(totals.total)}</span>
                </div>
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Success rate</span>
                    <span className="ai-usage__stat-value">{formatPct(totals.success, totals.total)}</span>
                </div>
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Avg latency</span>
                    <span className="ai-usage__stat-value">{formatMs(totals.avgLatencyMs)}</span>
                </div>
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Errors</span>
                    <span className="ai-usage__stat-value">{formatInt(totals.error)}</span>
                </div>
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Tokens in</span>
                    <span className="ai-usage__stat-value">{formatInt(totals.tokensIn)}</span>
                </div>
                <div className="ai-usage__stat">
                    <span className="ai-usage__stat-label">Tokens out</span>
                    <span className="ai-usage__stat-value">{formatInt(totals.tokensOut)}</span>
                </div>
            </div>

            <h6 className="ai-usage__section-title">By provider</h6>
            {window.perProvider.length === 0 ? (
                <p className="ai-usage__empty">No requests recorded in this window.</p>
            ) : (
                <table className="ai-usage__table">
                    <thead>
                        <tr>
                            <th scope="col">Provider</th>
                            <th scope="col">Trend</th>
                            <th scope="col">Requests</th>
                            <th scope="col">Success</th>
                            <th scope="col">Errors</th>
                            <th scope="col">Avg latency</th>
                        </tr>
                    </thead>
                    <tbody>
                        {window.perProvider.map((row) => {
                            const sparkValues = WINDOWS.map(w =>
                                (data.windows[w.key]?.perProvider || []).find(p => p.providerIdentifier === row.providerIdentifier)?.total || 0
                            );
                            return (
                                <tr key={row.providerIdentifier}>
                                    <td>{PROVIDER_LABELS[row.providerIdentifier] ?? row.providerIdentifier}</td>
                                    <td><Sparkline values={sparkValues} /></td>
                                    <td>{formatInt(row.total)}</td>
                                    <td>{formatPct(row.success, row.total)}</td>
                                    <td>{formatInt(row.error)}</td>
                                    <td>{formatMs(row.avgLatencyMs)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            )}
        </div>
    );
}
