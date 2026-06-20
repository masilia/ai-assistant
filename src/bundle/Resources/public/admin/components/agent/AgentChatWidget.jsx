import React, { useState, useRef, useEffect, useCallback } from 'react';
import { AI_ROUTES } from '../ai-settings/api-routes.js';
import { cleanErrorMessage } from '../ai-settings/constants.js';
import MessageBubble from './MessageBubble.jsx';
import ToolOutput from './ToolOutput.jsx';
import ActionConfirmDialog from './ActionConfirmDialog.jsx';

/**
 * @typedef {{ role: 'user'|'agent', content: string, timestamp: string, isError?: boolean, options?: Array<{label: string, value: string}>, toolOutputs?: Array<{tool: string, output: object}> }} ChatMessage
 */

const EXAMPLES = [
    'Create a team page with hero and cards',
    'Find all articles about climate',
    'What block types are available?',
    'Undo that last page creation',
];

function AgentChatWidget() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState(/** @type {ChatMessage[]} */ ([]));
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [confirmAction, setConfirmAction] = useState(null);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(/** @type {AbortController|null} */ (null));

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [messages, scrollToBottom]);

    useEffect(() => {
        if (isOpen && inputRef.current) {
            inputRef.current.focus();
        }
    }, [isOpen]);

    // Escape key closes panel
    useEffect(() => {
        if (!isOpen) return;

        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                setIsOpen(false);
            }
        };

        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [isOpen]);

    // Fetch chat history from backend on mount
    useEffect(() => {
        fetch(AI_ROUTES.agentHistory)
            .then((res) => res.json())
            .then((data) => {
                if (data.messages && data.messages.length > 0) {
                    setMessages(data.messages);
                }
            })
            .catch(() => {});
    }, []);

    const getTimestamp = () => {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const addMessage = useCallback((role, content, extra = {}) => {
        setMessages((prev) => [
            ...prev,
            { role, content, timestamp: getTimestamp(), ...extra },
        ]);
    }, []);

    const postChat = useCallback(async (body) => {
        const controller = new AbortController();
        abortRef.current = controller;

        try {
            const res = await fetch(AI_ROUTES.agentChat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                signal: controller.signal,
            });

            const data = await res.json();

            if (!res.ok) {
                addMessage('agent', cleanErrorMessage(data.error || 'Request failed'), { isError: true });
                return;
            }

            if (data.results && data.results.length > 0) {
                addMessage('agent', data.message || 'Done!', {
                    toolOutputs: data.results,
                    ...(data.options ? { options: data.options } : {}),
                });
            } else {
                addMessage('agent', data.message || 'Done.', {
                    ...(data.options ? { options: data.options } : {}),
                });
            }
        } finally {
            abortRef.current = null;
        }
    }, [addMessage]);

    const handleSend = useCallback(async () => {
        const text = input.trim();
        if (!text || loading) return;

        setInput('');
        addMessage('user', text);
        setLoading(true);

        try {
            await postChat({ message: text });
        } catch (err) {
            if (err.name !== 'AbortError') {
                addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
            }
        } finally {
            setLoading(false);
        }
    }, [input, loading, addMessage, postChat]);

    const handleOptionSelect = useCallback(async (value) => {
        if (loading) return;

        setLoading(true);

        try {
            await postChat({ message: value, selected_option: value });
        } catch (err) {
            if (err.name !== 'AbortError') {
                addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
            }
        } finally {
            setLoading(false);
        }
    }, [loading, addMessage, postChat]);

    const handleCancel = useCallback(() => {
        if (abortRef.current) {
            abortRef.current.abort();
            abortRef.current = null;
            setLoading(false);
            addMessage('agent', 'Cancelled.');
        }
    }, [addMessage]);

    const handleUndo = useCallback(() => {
        setConfirmAction({
            message: 'Undo the last action? This cannot be undone.',
            onConfirm: async () => {
                setLoading(true);
                addMessage('user', '/undo');

                try {
                    const res = await fetch(AI_ROUTES.agentChat, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ message: 'undo' }),
                    });

                    const data = await res.json();

                    if (!res.ok) {
                        addMessage('agent', cleanErrorMessage(data.error || 'Undo failed'), { isError: true });
                        return;
                    }

                    addMessage('agent', data.message || 'Undone.');
                } catch (err) {
                    addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
                } finally {
                    setLoading(false);
                }
            },
        });
    }, [addMessage]);

    const handleNewConversation = useCallback(() => {
        setConfirmAction({
            message: 'Start a new conversation? Current chat will be cleared.',
            onConfirm: async () => {
                try {
                    await fetch(AI_ROUTES.agentClear, { method: 'POST' });
                } catch {
                    // ignore clear errors
                }
                setMessages([]);
                setInput('');
            },
        });
    }, []);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    }, [handleSend]);

    // Auto-resize textarea
    const handleInputChange = useCallback((e) => {
        setInput(e.target.value);
        const el = e.target;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }, []);

    const handleExampleClick = useCallback((example) => {
        setInput(example);
        if (inputRef.current) {
            inputRef.current.focus();
            inputRef.current.style.height = 'auto';
            inputRef.current.style.height = Math.min(inputRef.current.scrollHeight, 120) + 'px';
        }
    }, []);

    return (
        <div className="agent-chat">
            <button
                type="button"
                className="agent-chat__toggle"
                onClick={() => setIsOpen((prev) => !prev)}
                title="AI Agent"
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M12 8V4H8" />
                    <rect width="16" height="12" x="4" y="8" rx="2" />
                    <path d="M2 14h2" />
                    <path d="M20 14h2" />
                    <path d="M15 13v2" />
                    <path d="M9 13v2" />
                </svg>
            </button>

            {isOpen && (
                <div className="agent-chat__panel">
                    <div className="agent-chat__header">
                        <span className="agent-chat__header-title">AI Agent</span>
                        <div className="agent-chat__header-actions">
                            <button
                                type="button"
                                className="agent-chat__header-btn"
                                onClick={handleNewConversation}
                                disabled={loading}
                                title="New conversation"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M12 5v14" />
                                    <path d="M5 12h14" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                className="agent-chat__header-btn"
                                onClick={handleUndo}
                                disabled={loading || messages.length === 0}
                                title="Undo last action"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 7v6h6" />
                                    <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                className="agent-chat__header-btn"
                                onClick={() => setIsOpen(false)}
                                title="Close"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M18 6 6 18" />
                                    <path d="m6 6 12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div className="agent-chat__messages">
                        {messages.length === 0 && (
                            <div className="agent-chat__empty">
                                <p>Ask me to create pages, manage content, or list available blocks.</p>
                                <p className="agent-chat__empty-hint">Try one of these:</p>
                                <ul className="agent-chat__empty-examples">
                                    {EXAMPLES.map((ex) => (
                                        <li key={ex}>
                                            <button
                                                type="button"
                                                className="agent-chat__example-btn"
                                                onClick={() => handleExampleClick(ex)}
                                            >
                                                {ex}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {messages.map((msg, index) => (
                            <div key={index} className="agent-chat__message">
                                <MessageBubble
                                    role={msg.role}
                                    content={msg.content}
                                    timestamp={msg.timestamp}
                                    isError={msg.isError}
                                />
                                {msg.options && msg.options.length > 0 && (
                                    <div className="agent-chat__options">
                                        {msg.options.map((opt) => (
                                            <button
                                                key={opt.value}
                                                type="button"
                                                className="agent-chat__option-btn"
                                                onClick={() => handleOptionSelect(opt.value)}
                                                disabled={loading}
                                            >
                                                {opt.label}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {msg.toolOutputs && msg.toolOutputs.map((output, outIdx) => (
                                    <ToolOutput
                                        key={outIdx}
                                        toolName={output.tool}
                                        output={output.output}
                                    />
                                ))}
                            </div>
                        ))}

                        {loading && (
                            <div className="agent-chat__typing">
                                <span className="agent-chat__typing-dot" />
                                <span className="agent-chat__typing-dot" />
                                <span className="agent-chat__typing-dot" />
                                <button
                                    type="button"
                                    className="agent-chat__cancel-btn"
                                    onClick={handleCancel}
                                    title="Cancel"
                                >
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg>
                                </button>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    <div className="agent-chat__input-area">
                        <textarea
                            ref={inputRef}
                            className="agent-chat__input"
                            value={input}
                            onChange={handleInputChange}
                            onKeyDown={handleKeyDown}
                            placeholder="Ask the agent..."
                            rows={1}
                            disabled={loading}
                        />
                        <button
                            type="button"
                            className="agent-chat__send"
                            onClick={handleSend}
                            disabled={loading || !input.trim()}
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="m22 2-7 20-4-9-9-4Z" />
                                <path d="M22 2 11 13" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            {confirmAction && (
                <ActionConfirmDialog
                    message={confirmAction.message}
                    onConfirm={() => {
                        confirmAction.onConfirm();
                        setConfirmAction(null);
                    }}
                    onCancel={() => setConfirmAction(null)}
                />
            )}
        </div>
    );
}

export default AgentChatWidget;
