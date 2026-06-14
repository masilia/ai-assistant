import React from 'react';

/**
 * Minimal markdown renderer for agent messages.
 * Handles **bold**, bullet lists, and category headers.
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

        // Empty line: flush list
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

        // Bullet item: starts with -
        if (trimmed.startsWith('- ')) {
            inList = true;
            listItems.push(trimmed.slice(2));
            return;
        }

        // Regular line
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
 * Render inline formatting: **bold** and `code`.
 */
function renderInline(text) {
    const parts = [];
    const regex = /(\*\*[^*]+\*\*|`[^`]+`)/g;
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
        }

        lastIndex = match.index + token.length;
    }

    if (lastIndex < text.length) {
        parts.push(text.slice(lastIndex));
    }

    return parts.length > 0 ? parts : text;
}

/**
 * Renders a single chat message (user or agent).
 *
 * @param {{ role: 'user'|'agent', content: string, timestamp?: string, isError?: boolean }} props
 */
function MessageBubble({ role, content, timestamp, isError }) {
    const isUser = role === 'user';

    return (
        <div className={`agent-chat__bubble agent-chat__bubble--${isUser ? 'user' : 'agent'}${isError ? ' agent-chat__bubble--error' : ''}`}>
            <div className="agent-chat__bubble-header">
                <span className="agent-chat__bubble-role">{isUser ? 'You' : 'Agent'}</span>
                {timestamp && <span className="agent-chat__bubble-time">{timestamp}</span>}
            </div>
            <div className="agent-chat__bubble-content">
                {isUser ? content : renderContent(content)}
            </div>
        </div>
    );
}

export default MessageBubble;
