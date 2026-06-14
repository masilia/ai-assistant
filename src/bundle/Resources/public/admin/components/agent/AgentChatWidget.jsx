import React, { useState, useRef, useEffect, useCallback } from 'react';
import { AI_ROUTES } from '../ai-settings/api-routes.js';
import { cleanErrorMessage } from '../ai-settings/constants.js';
import MessageBubble from './MessageBubble.jsx';
import PlanDisplay from './PlanDisplay.jsx';
import ToolOutput from './ToolOutput.jsx';
import ActionConfirmDialog from './ActionConfirmDialog.jsx';

/**
 * @typedef {{ role: 'user'|'agent', content: string, timestamp: string, isError?: boolean, plan?: object, toolOutputs?: Array<{tool: string, output: object}> }} ChatMessage
 */

/**
 * Main agent chat widget — floating button + slide-out panel.
 */
function AgentChatWidget() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState(/** @type {ChatMessage[]} */ ([]));
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [pendingPlan, setPendingPlan] = useState(null);
    const [confirmAction, setConfirmAction] = useState(null);

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

    const getTimestamp = () => {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const addMessage = useCallback((role, content, extra = {}) => {
        setMessages((prev) => [
            ...prev,
            { role, content, timestamp: getTimestamp(), ...extra },
        ]);
    }, []);

    const handleSend = useCallback(async () => {
        const text = input.trim();
        if (!text || loading) return;

        setInput('');
        addMessage('user', text);
        setLoading(true);

        try {
            const res = await fetch(AI_ROUTES.agentChat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text }),
            });

            const data = await res.json();

            if (!res.ok) {
                addMessage('agent', cleanErrorMessage(data.error || 'Request failed'), { isError: true });
                return;
            }

            if (data.plan) {
                setPendingPlan(data.plan);
                addMessage('agent', data.message || 'I have a plan for you. Please review it below.', {
                    plan: data.plan,
                });
            } else if (data.results && data.results.length > 0) {
                addMessage('agent', data.message || 'Done!', {
                    toolOutputs: data.results,
                });
            } else {
                addMessage('agent', data.message || 'Done.');
            }
        } catch (err) {
            addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
        } finally {
            setLoading(false);
        }
    }, [input, loading, addMessage]);

    const handlePlanConfirm = useCallback(async () => {
        if (!pendingPlan) return;

        const plan = pendingPlan;
        setPendingPlan(null);
        setLoading(true);

        addMessage('agent', 'Executing plan...');

        try {
            const res = await fetch(AI_ROUTES.agentExecute, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    steps: plan.steps,
                    description: plan.description,
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                addMessage('agent', cleanErrorMessage(data.error || 'Execution failed'), { isError: true });
                return;
            }

            if (data.results && data.results.length > 0) {
                addMessage('agent', data.message || 'Plan executed successfully!', {
                    toolOutputs: data.results,
                });
            } else {
                addMessage('agent', data.message || 'Plan executed successfully!');
            }
        } catch (err) {
            addMessage('agent', cleanErrorMessage(err.message || 'Network error'), { isError: true });
        } finally {
            setLoading(false);
        }
    }, [pendingPlan, addMessage]);

    const handlePlanCancel = useCallback(() => {
        setPendingPlan(null);
        addMessage('agent', 'Plan cancelled.');
    }, [addMessage]);

    const handleUndo = useCallback(async () => {
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
    }, [addMessage]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    }, [handleSend]);

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
                                onClick={handleUndo}
                                disabled={loading}
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
                                <p className="agent-chat__empty-hint">Examples:</p>
                                <ul className="agent-chat__empty-examples">
                                    <li>"Create a team page with hero and cards"</li>
                                    <li>"Find all articles about climate"</li>
                                    <li>"What block types are available?"</li>
                                    <li>"Undo that last page creation"</li>
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
                                {msg.plan && (
                                    <PlanDisplay
                                        plan={msg.plan}
                                        onConfirm={handlePlanConfirm}
                                        onCancel={handlePlanCancel}
                                    />
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

                        {loading && !pendingPlan && (
                            <div className="agent-chat__typing">
                                <span className="agent-chat__typing-dot" />
                                <span className="agent-chat__typing-dot" />
                                <span className="agent-chat__typing-dot" />
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    <div className="agent-chat__input-area">
                        <textarea
                            ref={inputRef}
                            className="agent-chat__input"
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
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
