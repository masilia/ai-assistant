import React, { useState, useReducer, useRef, useEffect, useCallback } from 'react';
import { AI_ROUTES } from '../ai-settings/api-routes.js';
import { cleanErrorMessage } from '../ai-settings/constants.js';
import { useSSEStream } from '../shared/useSSEStream.js';
import MessageBubble from './MessageBubble.jsx';
import ToolOutput from './ToolOutput.jsx';
import ActionConfirmDialog from './ActionConfirmDialog.jsx';
import BrainVisualization from './BrainVisualization.jsx';

/**
 * @typedef {{ role: 'user'|'agent', content: string, timestamp: string, isError?: boolean, errorType?: string|null, options?: Array<{label: string, value: string}>, toolOutputs?: Array<{tool: string, output: object}> }} ChatMessage
 * @typedef {{ steps: Array<{tool: string, call_id: string, loading: boolean, result?: object, progressMessage?: string}>, message: string, options?: Array<{label: string, value: string}>, error: boolean, errorType?: string, done: boolean } | null} StreamingState
 */

/**
 * Reducer for the in-flight streaming bubble state.
 * All mutations go through dispatch — no more mutable ref + forceRender.
 */
function streamingReducer(state, action) {
    if (action.type === 'START') {
        return { steps: [], message: '', error: false, errorType: null, done: false };
    }
    if (action.type === 'CLEAR') {
        return null;
    }
    if (state === null) return null;

    switch (action.type) {
        case 'STEP_START':
            return { ...state, steps: [...state.steps, { tool: action.tool, call_id: action.call_id, loading: true }] };
        case 'STEP_PROGRESS': {
            if (state.steps.length === 0) return state;
            const steps = state.steps.map((s, i) =>
                i === state.steps.length - 1 ? { ...s, progressMessage: action.message } : s
            );
            return { ...state, steps };
        }
        case 'STEP_RESULT': {
            const steps = state.steps.map((s) =>
                s.call_id === action.call_id
                    ? { ...s, loading: false, result: action.result, progressMessage: undefined }
                    : s
            );
            return { ...state, steps };
        }
        case 'MESSAGE':
            return { ...state, message: action.message };
        case 'OPTIONS':
            return { ...state, message: action.message, options: action.options };
        case 'ERROR':
            return { ...state, message: action.message, error: true, errorType: action.errorType || 'service_error' };
        case 'DONE':
            return { ...state, done: true };
        case 'NETWORK_ERROR':
            return { ...state, message: action.message, error: true, errorType: 'service_error', done: true };
        default:
            return state;
    }
}

const EXAMPLES = [
    'Create a team page with hero and cards',
    'Find all articles about climate',
    'What block types are available?',
    'Undo that last page creation',
];

const SLASH_COMMANDS = [
    { cmd: '/undo',   label: 'Undo last action',     description: 'Revert the most recent agent action' },
    { cmd: '/blocks', label: 'List block types',     description: 'Show all available block types' },
    { cmd: '/create', label: 'Create content',       description: 'Start the content creation wizard' },
    { cmd: '/search', label: 'Search content',       description: 'Search for content across the site' },
    { cmd: '/clear',  label: 'Clear conversation',   description: 'Start a fresh conversation' },
];

function AgentChatWidget() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState(/** @type {ChatMessage[]} */ ([]));
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [historyLoading, setHistoryLoading] = useState(true);
    const [confirmAction, setConfirmAction] = useState(null);
    const [streamKey, setStreamKey] = useState(0);
    const [lastFailedInput, setLastFailedInput] = useState('');
    const [streaming, dispatchStreaming] = useReducer(streamingReducer, null);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

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
            .catch(() => {})
            .finally(() => setHistoryLoading(false));
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

    const startStreaming = useCallback(() => {
        dispatchStreaming({ type: 'START' });
        setStreamKey((k) => k + 1);
    }, []);

    const { stream: sseStream, cancel: sseCancel } = useSSEStream({
        onEvent: useCallback((event) => {
            switch (event.type) {
                case 'step_start':    dispatchStreaming({ type: 'STEP_START', tool: event.tool, call_id: event.call_id }); break;
                case 'step_progress': dispatchStreaming({ type: 'STEP_PROGRESS', message: event.message }); break;
                case 'step_result':   dispatchStreaming({ type: 'STEP_RESULT', call_id: event.call_id, result: event.result }); break;
                case 'message':       dispatchStreaming({ type: 'MESSAGE', message: event.message }); break;
                case 'options':       dispatchStreaming({ type: 'OPTIONS', message: event.message, options: event.options }); break;
                case 'error':         dispatchStreaming({ type: 'ERROR', message: event.message, errorType: event.error_type }); break;
                default: break;
            }
        }, []),
        onDone: useCallback(() => {
            dispatchStreaming({ type: 'DONE' });
        }, []),
        onError: useCallback((message) => {
            dispatchStreaming({ type: 'NETWORK_ERROR', message: cleanErrorMessage(message) });
        }, []),
    });

    // Commit the streamed message to history when done, then wait for
    // BrainVisualization's exit animation before clearing (CLEAR unmounts it).
    const committedRef = useRef(false);
    useEffect(() => {
        if (streaming && streaming.done && !committedRef.current) {
            committedRef.current = true;
            const s = streaming;
            const toolOutputs = s.steps.map((step) => ({
                tool: step.tool,
                output: step.result?.data || step.result || {},
            }));
            addMessage('agent', s.message || 'Done.', {
                isError: s.error,
                errorType: s.errorType || null,
                ...(s.options ? { options: s.options } : {}),
                ...(toolOutputs.length > 0 ? { toolOutputs } : {}),
            });
        }
        if (!streaming) {
            committedRef.current = false;
        }
    }, [streaming, addMessage]);

    const finalizeStreaming = useCallback(() => {
        dispatchStreaming({ type: 'CLEAR' });
    }, []);

    const postChatStream = useCallback(async (body) => {
        startStreaming();
        await sseStream(AI_ROUTES.agentChat, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
    }, [startStreaming, sseStream]);

    const sendMessage = useCallback(async (text) => {
        if (!text || loading) return;

        setLastFailedInput('');
        addMessage('user', text);
        setLoading(true);

        try {
            await postChatStream({ message: text });
        } catch (err) {
            if (err.name !== 'AbortError') {
                setLastFailedInput(text);
                addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
            }
        } finally {
            setLoading(false);
        }
    }, [loading, addMessage, postChatStream]);

    const handleSend = useCallback(async () => {
        const text = input.trim();
        if (!text || loading) return;
        setInput('');
        await sendMessage(text);
    }, [input, loading, sendMessage]);

    const handleOptionSelect = useCallback(async (value) => {
        if (loading) return;

        setLoading(true);

        try {
            await postChatStream({ message: value, selected_option: value });
        } catch (err) {
            if (err.name !== 'AbortError') {
                addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
            }
        } finally {
            setLoading(false);
        }
    }, [loading, addMessage, postChatStream]);

    const handleCancel = useCallback(() => {
        sseCancel();
        setLoading(false);
        addMessage('agent', 'Cancelled.');
    }, [addMessage, sseCancel]);

    const handleUndo = useCallback(() => {
        setConfirmAction({
            message: 'Undo the last action? This cannot be undone.',
            onConfirm: async () => {
                setLoading(true);
                addMessage('user', '/undo');

                try {
                    await postChatStream({ message: 'undo' });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
                    }
                } finally {
                    setLoading(false);
                }
            },
        });
    }, [addMessage, postChatStream]);

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

    const slashOpen = input.startsWith('/') && !input.includes(' ') && !loading;
    const filteredCommands = slashOpen
        ? SLASH_COMMANDS.filter(c => c.cmd.startsWith(input))
        : [];

    const handleSlashSelect = useCallback((cmd) => {
        if (cmd.cmd === '/clear') {
            handleNewConversation();
            setInput('');
        } else {
            setInput(cmd.cmd + ' ');
            inputRef.current?.focus();
        }
    }, [handleNewConversation]);

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
                        {historyLoading && (
                            <div className="agent-chat__skeleton" aria-label="Loading conversation history" aria-busy="true">
                                <div className="agent-chat__skeleton-bubble agent-chat__skeleton-bubble--agent" />
                                <div className="agent-chat__skeleton-bubble agent-chat__skeleton-bubble--user" />
                                <div className="agent-chat__skeleton-bubble agent-chat__skeleton-bubble--agent agent-chat__skeleton-bubble--short" />
                            </div>
                        )}

                        {!historyLoading && messages.length === 0 && (
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

                        {messages.map((msg, index) => {
                            const prevMsg = index > 0 ? messages[index - 1] : null;
                            const isGrouped = prevMsg && prevMsg.role === msg.role;
                            const isLastError = msg.isError && index === messages.length - 1;

                            return (
                            <div key={index} className={`agent-chat__message${isGrouped ? ' agent-chat__message--grouped' : ''}`}>
                                <MessageBubble
                                    role={msg.role}
                                    content={msg.content}
                                    timestamp={msg.timestamp}
                                    isError={msg.isError}
                                    grouped={isGrouped}
                                />
                                {isLastError && msg.errorType === 'loop_exhausted' && (
                                    <div className="agent-chat__error-actions">
                                        <span className="agent-chat__error-hint">Try rephrasing your request or start fresh.</span>
                                        <div className="agent-chat__error-btns">
                                            {lastFailedInput && (
                                                <button
                                                    type="button"
                                                    className="agent-chat__retry-btn"
                                                    onClick={() => {
                                                        inputRef.current?.focus();
                                                        setInput(lastFailedInput);
                                                    }}
                                                    disabled={loading}
                                                >
                                                    Edit &amp; Rephrase
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="agent-chat__retry-btn agent-chat__retry-btn--secondary"
                                                onClick={handleNewConversation}
                                                disabled={loading}
                                            >
                                                Start Over
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {isLastError && msg.errorType !== 'loop_exhausted' && lastFailedInput && (
                                    <button
                                        type="button"
                                        className="agent-chat__retry-btn"
                                        onClick={() => sendMessage(lastFailedInput)}
                                        disabled={loading}
                                    >
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                                            <path d="M3 3v5h5" />
                                        </svg>
                                        Retry
                                    </button>
                                )}
                                {msg.options && msg.options.length > 0 && (
                                    <div
                                        className="agent-chat__options"
                                        onKeyDown={(e) => {
                                            if (loading) return;
                                            const btns = /** @type {HTMLButtonElement[]} */ (e.currentTarget.querySelectorAll('button'));
                                            const idx = btns.indexOf(/** @type {HTMLButtonElement} */ (document.activeElement));
                                            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                                                e.preventDefault();
                                                btns[(idx + 1) % btns.length]?.focus();
                                            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                                                e.preventDefault();
                                                btns[(idx - 1 + btns.length) % btns.length]?.focus();
                                            }
                                        }}
                                    >
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
                                {msg.toolOutputs && msg.toolOutputs.length > 0 && (
                                    <div className="agent-chat__tool-outputs">
                                        {msg.toolOutputs.length > 1 && (
                                            <div className="agent-chat__tool-summary">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                    <path d="M20 6 9 17l-5-5" />
                                                </svg>
                                                <span>{msg.toolOutputs.length} actions completed</span>
                                            </div>
                                        )}
                                        {msg.toolOutputs.map((output, outIdx) => (
                                            <ToolOutput
                                                key={outIdx}
                                                toolName={output.tool}
                                                output={output.output}
                                                stepIndex={outIdx}
                                                totalSteps={msg.toolOutputs.length}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                            );
                        })}

                        {streaming && (
                            <BrainVisualization
                                key={streamKey}
                                steps={streaming.steps}
                                message={streaming.message}
                                isComplete={streaming.done}
                                onExit={finalizeStreaming}
                            />
                        )}

                        {loading && !streaming && (
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
                        {filteredCommands.length > 0 && (
                            <div className="agent-chat__slash-menu" role="listbox" aria-label="Slash commands">
                                {filteredCommands.map((cmd) => (
                                    <button
                                        key={cmd.cmd}
                                        type="button"
                                        className="agent-chat__slash-item"
                                        role="option"
                                        aria-selected="false"
                                        onClick={() => handleSlashSelect(cmd)}
                                    >
                                        <span className="agent-chat__slash-cmd">{cmd.cmd}</span>
                                        <span className="agent-chat__slash-desc">{cmd.description}</span>
                                    </button>
                                ))}
                            </div>
                        )}
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
