import React from 'react';
import ReactDOM from 'react-dom';
import AgentChatWidget from '../components/agent/AgentChatWidget.jsx';

function mountAgentChat() {
    const anchor = document.querySelector('.ibexa-quick-action-menu');
    if (!anchor) return;

    // Skip if already mounted
    if (anchor.querySelector('.agent-chat')) return;

    const container = document.createElement('div');
    anchor.appendChild(container);

    if (typeof ReactDOM.createRoot === 'function') {
        ReactDOM.createRoot(container).render(<AgentChatWidget />);
    } else {
        ReactDOM.render(<AgentChatWidget />, container);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAgentChat);
} else {
    mountAgentChat();
}
