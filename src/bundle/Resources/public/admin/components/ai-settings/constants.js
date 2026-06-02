/**
 * Shared constants for AI Settings dashboard.
 * Single source of truth for provider types — used in filters, drawers, and cards.
 */
export const PROVIDER_TYPES = [
    { value: 'openai',    label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'ollama',    label: 'Ollama' },
    { value: 'mistral',   label: 'Mistral' },
    { value: 'minimax',   label: 'MiniMax' },
];

export function getProviderLabel(identifier) {
    const found = PROVIDER_TYPES.find(t => t.value === identifier);
    return found ? found.label : identifier;
}

/**
 * Quick action presets for AI content suggestions.
 * Each action has a label (display) and a promptTemplate (sent to the AI).
 * Templates can use {fieldName} and {contentType} placeholders.
 */
export const QUICK_ACTIONS = [
    {
        id: 'improve',
        label: 'Improve',
        icon: '✨',
        promptTemplate: 'Improve the clarity, engagement, and professional tone of this text.',
    },
    {
        id: 'shorten',
        label: 'Shorten',
        icon: '↗',
        promptTemplate: 'Make this text more concise while preserving all key information and meaning.',
    },
    {
        id: 'lengthen',
        label: 'Lengthen',
        icon: '↔',
        promptTemplate: 'Expand this text with more detail, examples, and thorough explanations.',
    },
    {
        id: 'fix_grammar',
        label: 'Fix Grammar',
        icon: '✏️',
        promptTemplate: 'Fix all grammar, spelling, and punctuation errors in this text.',
    },
    {
        id: 'formal',
        label: 'Formal',
        icon: '👔',
        promptTemplate: 'Rephrase this text in a formal, professional tone.',
    },
    {
        id: 'casual',
        label: 'Casual',
        icon: '😊',
        promptTemplate: 'Rephrase this text in a casual, conversational tone.',
    },
    {
        id: 'summarize',
        label: 'Summarize',
        icon: '📝',
        promptTemplate: 'Provide a concise summary of this text in 2-3 sentences.',
    },
    {
        id: 'translate',
        label: 'Translate',
        icon: '🌐',
        promptTemplate: 'TRANSLATE', // Special: replaced by modal with source language
        isTranslation: true,
    },
];

export function applyQuickAction(quickActionId, fieldContext = {}) {
    const action = QUICK_ACTIONS.find(q => q.id === quickActionId);
    if (!action) return '';
    let prompt = action.promptTemplate;
    if (fieldContext.fieldName) {
        prompt = prompt.replace('{fieldName}', fieldContext.fieldName);
    }
    if (fieldContext.contentTypeName) {
        prompt = prompt.replace('{contentType}', fieldContext.contentTypeName);
    }
    return prompt;
}

/**
 * Dispatch an 'ibexa-notify' custom event on document.body.
 * This triggers the built-in Ibexa Admin notification system.
 * @param {('success'|'error'|'warning'|'info')} type
 * @param {string} message
 */
export function notify(type, message) {
    const label = type === 'danger' ? 'error' : type;
    const event = new CustomEvent('ibexa-notify', {
        detail: { message, label }
    });
    document.body.dispatchEvent(event);
}

/**
 * Format raw error messages, especially OpenAI/Anthropic/Mistral JSON payloads,
 * into a clean, human-readable format.
 * Also handles the structured { error: { code, message } } envelope from the BE.
 * @param {string|object} message
 * @returns {string}
 */
export function cleanErrorMessage(message) {
    if (!message) return 'Unknown error occurred.';

    // Handle structured error envelope from BE: { error: { code, message } }
    if (typeof message === 'object' && message !== null) {
        if (message.error?.message) return message.error.message;
        if (message.message) return message.message;
        return JSON.stringify(message);
    }

    const trimMsg = String(message).trim();

    // Check if entire message is JSON
    if (trimMsg.startsWith('{') || trimMsg.startsWith('[')) {
        try {
            const parsed = JSON.parse(trimMsg);
            if (parsed.error && parsed.error.message) {
                return parsed.error.message;
            }
            if (parsed.message) {
                return parsed.message;
            }
        } catch (e) {
            // fallback
        }
    }

    // Check if there is a JSON part after a prefix (e.g. "API returned HTTP 401: {...}")
    const jsonStart = trimMsg.indexOf('{');
    if (jsonStart !== -1) {
        const prefix = trimMsg.substring(0, jsonStart).trim();
        const jsonPart = trimMsg.substring(jsonStart).trim();
        try {
            const parsed = JSON.parse(jsonPart);
            if (parsed.error && parsed.error.message) {
                return `${prefix} ${parsed.error.message}`;
            }
            if (parsed.message) {
                return `${prefix} ${parsed.message}`;
            }
        } catch (e) {
            // fallback
        }
    }

    return trimMsg;
}


