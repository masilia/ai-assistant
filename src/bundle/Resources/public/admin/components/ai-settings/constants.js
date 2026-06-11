/**
 * Apply-mode constants: how the AI response is distributed onto the DOM.
 * - WHOLE_BLOCK: parse a JSON object and distribute values to all matching inputs.
 * - SUB_FIELD:   extract one value for a single targeted input.
 */
export const APPLY_MODE = Object.freeze({
    WHOLE_BLOCK: 'whole-block',
    SUB_FIELD: 'sub-field',
});

/**
 * Suggest-mode constants: how the generated text is merged with existing content.
 */
export const SUGGEST_MODE = Object.freeze({
    REPLACE: 'replace',
    APPEND: 'append',
});

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
 *
 * The `icon` field is a React component reference (Lucide-style
 * SVG component from ./icons.jsx), not a string. Consumers MUST
 * render <action.icon /> rather than the raw value. Importing
 * ./icons.jsx here would create a circular dependency between
 * constants.js and icons.jsx (icons.jsx re-exports the
 * components but is also imported by consumers).
 */
import {
    WandIcon,
    MinimizeIcon,
    MaximizeIcon,
    SpellCheckIcon,
    BriefcaseIcon,
    SmileIcon,
    FileTextIcon,
    LanguagesIcon,
    ImageIcon,
} from './icons.jsx';

export const QUICK_ACTIONS = [
    {
        id: 'improve',
        label: 'Improve',
        icon: WandIcon,
        promptTemplate: 'Improve the clarity, engagement, and professional tone of this text.',
    },
    {
        id: 'shorten',
        label: 'Shorten',
        icon: MinimizeIcon,
        promptTemplate: 'Make this text more concise while preserving all key information and meaning.',
    },
    {
        id: 'lengthen',
        label: 'Lengthen',
        icon: MaximizeIcon,
        promptTemplate: 'Expand this text with more detail, examples, and thorough explanations.',
    },
    {
        id: 'fix_grammar',
        label: 'Fix Grammar',
        icon: SpellCheckIcon,
        promptTemplate: 'Fix all grammar, spelling, and punctuation errors in this text.',
    },
    {
        id: 'formal',
        label: 'Formal',
        icon: BriefcaseIcon,
        promptTemplate: 'Rephrase this text in a formal, professional tone.',
    },
    {
        id: 'casual',
        label: 'Casual',
        icon: SmileIcon,
        promptTemplate: 'Rephrase this text in a casual, conversational tone.',
    },
    {
        id: 'summarize',
        label: 'Summarize',
        icon: FileTextIcon,
        promptTemplate: 'Provide a concise summary of this text in 2-3 sentences.',
    },
    {
        id: 'translate',
        label: 'Translate',
        icon: LanguagesIcon,
        promptTemplate: 'TRANSLATE', // Special: replaced by modal with source language
        isTranslation: true,
    },
    {
        id: 'generate_image',
        label: 'Generate Image',
        icon: ImageIcon,
        promptTemplate: 'GENERATE_IMAGE', // Special: triggers image generation flow
        isImageGeneration: true,
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
    if (typeof message === 'object') {
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


