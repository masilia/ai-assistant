import { useCallback, useRef, useState } from 'react';
import { AI_ROUTES } from '../ai-settings/api-routes.js';
import { useSSEStream } from '../shared/useSSEStream.js';

/**
 * @typedef {import('../ai-settings/types.js').FieldContext} FieldContext
 */

/**
 * Owns the SSE streaming lifecycle for AI field suggestions.
 *
 * Delegates the low-level protocol (chunked decode, line buffering, UTF-8
 * tail flush) to the shared useSSEStream hook. This hook manages only the
 * suggestion-specific reactive state on top.
 *
 * @param {FieldContext|null} fieldContext   Field context payload (null until modal opens)
 * @param {string}            prompt         The current prompt text
 * @param {string}            sourceLanguage Source language for translation, or ''
 * @returns {{
 *   suggestion: string,
 *   streaming: boolean,
 *   loading: boolean,
 *   error: string,
 *   start: () => Promise<void>,
 *   stop: () => void,
 *   clear: () => void,
 *   setError: (message: string) => void,
 * }}
 */
export function useAiStream(fieldContext, prompt, sourceLanguage) {
    const [suggestion, setSuggestion] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const activeRef = useRef(false);

    const handleEvent = useCallback((event) => {
        if (!activeRef.current) return;

        if (event.error) {
            setError(event.error.message || 'An error occurred');
            setStreaming(false);
            return;
        }

        if (event.token) {
            setSuggestion((prev) => prev + event.token);
        }

        if (event.done) {
            setStreaming(false);
        }
    }, []);

    const handleDone = useCallback(() => {
        if (!activeRef.current) return;
        setStreaming(false);
        setLoading(false);
    }, []);

    const handleError = useCallback((message) => {
        if (!activeRef.current) return;
        setError(message);
        setStreaming(false);
        setLoading(false);
    }, []);

    const { stream, cancel } = useSSEStream({
        onEvent: handleEvent,
        onDone: handleDone,
        onError: handleError,
    });

    const stop = useCallback(() => {
        activeRef.current = false;
        cancel();
        setStreaming(false);
        setLoading(false);
    }, [cancel]);

    const clear = useCallback(() => {
        setSuggestion('');
        setError('');
        setLoading(false);
        setStreaming(false);
    }, []);

    const setErrorMessage = useCallback((message) => {
        setError(message);
    }, []);

    const start = useCallback(async () => {
        if (!prompt.trim() || !fieldContext) return;

        activeRef.current = true;
        setLoading(true);
        setStreaming(true);
        setError('');
        setSuggestion('');

        await stream(AI_ROUTES.suggestStream, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fieldType: fieldContext.fieldType,
                prompt: prompt.trim(),
                currentValue: fieldContext.currentValue,
                contentType: fieldContext.contentTypeName,
                fieldName: fieldContext.fieldName,
                language: fieldContext.language,
                contentTitle: fieldContext.contentTitle,
                siblingFields: fieldContext.siblingFields,
                contentId: fieldContext.contentId,
                sourceLanguage: sourceLanguage,
                subFieldKey: fieldContext.subFieldKey,
                metaKeys: fieldContext.metaKeys,
            }),
        });

        activeRef.current = false;
    }, [fieldContext, prompt, sourceLanguage, stream]);

    return { suggestion, streaming, loading, error, start, stop, clear, setError: setErrorMessage };
}
