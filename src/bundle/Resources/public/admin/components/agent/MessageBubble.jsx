import React, { useState, useCallback } from 'react';

/**
 * Minimal markdown renderer for agent messages.
 * Handles **bold**, bullet lists, `code`, and [links](url).
 */
function renderContent(text) {
    if (!text) return null;

    const lines = text.split('\n');
    const elements = [];
    let inList = false;
    let listItems = [];

    const flushList = () => {
        if (listItems.length > 0) {
            elements.push(
                <ul key={`list-${elements.length}`} className="agent-chat__list">
                    {listItems.map((item, i) => (
                        <li key={i} className="agent-chat__list-item">
                            {renderInline(item)}
                        </li>
                    ))}
                </ul>
            );
            listItems = [];
            inList = false;
        }
    };

    lines.forEach((line, idx) => {
        const trimmed = line.trim();

        if (trimmed === '') {
            flushList();
            return;
        }

        // Category header: **Hero:** or **Text:**
        if (/^\*\*[^*]+\*\*\s*$/.test(trimmed) || /^\*\*[^*]+\*\*:?\s*$/.test(trimmed)) {
            flushList();
            elements.push(
                <div key={idx} className="agent-chat__category">
                    {renderInline(trimmed)}
                </div>
            );
            return;
        }

        // Bullet item
        if (trimmed.startsWith('- ')) {
            inList = true;
            listItems.push(trimmed.slice(2));
            return;
        }

        flushList();
        elements.push(
            <div key={idx} className="agent-chat__text-line">
                {renderInline(trimmed)}
            </div>
        );
    });

    flushList();
    return elements;
}

/**
 * Render inline formatting: **bold**, `code`, and [text](url).
 */
function renderInline(text) {
    const parts = [];
    const regex = /(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push(text.slice(lastIndex, match.index));
        }

        const token = match[0];
        if (token.startsWith('**')) {
            parts.push(<strong key={match.index}>{token.slice(2, -2)}</strong>);
        } else if (token.startsWith('`')) {
            parts.push(<code key={match.index} className="agent-chat__code">{token.slice(1, -1)}</code>);
        } else if (token.startsWith('[')) {
            const linkMatch = token.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
            if (linkMatch) {
                parts.push(
                    <a
                        key={match.index}
                        href={linkMatch[2]}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="agent-chat__link"
                    >
                        {linkMatch[1]}
                    </a>
                );
            } else {
                parts.push(token);
            }
        }

        lastIndex = match.index + token.length;
    }

    if (lastIndex < text.length) {
        parts.push(text.slice(lastIndex));
    }

    return parts.length > 0 ? parts : text;
}

/**
 * Renders a single chat message (user or agent) with copy button.
 *
 * @param {{ role: 'user'|'agent', content: string, timestamp?: string, isError?: boolean }} props
 */
function MessageBubble({ role, content, timestamp, isError }) {
    const isUser = role === 'user';
    const [copied, setCopied] = useState(false);

    const handleCopy = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(content);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard API may fail in some contexts
        }
    }, [content]);

    return (
        <div className={`agent-chat__bubble agent-chat__bubble--${isUser ? 'user' : 'agent'}${isError ? ' agent-chat__bubble--error' : ''}`}>
            <div className="agent-chat__bubble-header">
                <span className="agent-chat__bubble-role">{isUser ? 'You' : 'Agent'}</span>
                {timestamp && <span className="agent-chat__bubble-time">{timestamp}</span>}
                {!isUser && (
                    <button
                        type="button"
                        className="agent-chat__copy-btn"
                        onClick={handleCopy}
                        title={copied ? 'Copied!' : 'Copy message'}
                    >
                        {copied ? (
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        ) : (
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <rect width="14" height="14" x="8" y="8" rx="2" />
                                <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                            </svg>
                        )}
                    </button>
                )}
            </div>
            <div className="agent-chat__bubble-content">
                {isUser ? content : renderContent(content)}
            </div>
        </div>
    );
}

export default MessageBubble;
