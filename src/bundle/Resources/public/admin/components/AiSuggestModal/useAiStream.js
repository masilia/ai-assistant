import { useCallback, useRef, useState } from 'react';
import { AI_ROUTES } from '../ai-settings/api-routes.js';

/**
 * @typedef {import('../ai-settings/types.js').FieldContext} FieldContext
 */

/**
 * Owns the SSE streaming lifecycle: fetch + ReadableStream reader +
 * TextDecoder + line buffering + abort.
 *
 * Returns reactive state (suggestion, streaming, loading, error)
 * and imperative start()/stop()/clear() methods. The shell component
 * stays pure presentational.
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

    const abortControllerRef = useRef(null);

    const stop = useCallback(() => {
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
        }
    }, []);

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

        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }
        abortControllerRef.current = new AbortController();

        setLoading(true);
        setStreaming(true);
        setError('');
        setSuggestion('');

        try {
            const res = await fetch(AI_ROUTES.suggestStream, {
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
                signal: abortControllerRef.current.signal,
            });

            if (!res.ok) {
                const data = await res.json();
                const errorMessage = data.error?.message
                    || (typeof data.error === 'string' ? data.error : 'An error occurred');
                setError(errorMessage);
                setStreaming(false);
                setLoading(false);
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            const processLines = (text) => {
                if (!text) return;
                const lines = text.split('\n');
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed.startsWith('data: ')) continue;

                    const jsonStr = trimmed.slice(6);
                    if (!jsonStr) continue;

                    try {
                        const data = JSON.parse(jsonStr);

                        if (data.error) {
                            const errorMessage = data.error.message || 'An error occurred';
                            setError(errorMessage);
                            setStreaming(false);
                            return;
                        }

                        if (data.token) {
                            setSuggestion((prev) => prev + data.token);
                        }

                        if (data.done) {
                            setStreaming(false);
                            return;
                        }
                    } catch (e) {
                        // Skip malformed JSON lines
                    }
                }
            };

            let streamDone = false;

            while (!streamDone) {
                const { done, value } = await reader.read();

                if (done) {
                    // Flush any bytes still held by the decoder (e.g. a trailing
                    // multi-byte UTF-8 sequence split across chunks) so the last
                    // characters of non-ASCII content are not silently dropped.
                    buffer += decoder.decode();
                    processLines(buffer);
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                processLines(lines.join('\n'));
            }
        } catch (err) {
            if (err.name === 'AbortError') {
                // Stream was cancelled, ignore
            } else {
                setError(err.message || 'Network error');
                setStreaming(false);
            }
        } finally {
            setLoading(false);
            setStreaming(false);
            abortControllerRef.current = null;
        }
    }, [fieldContext, prompt, sourceLanguage]);

    return { suggestion, streaming, loading, error, start, stop, clear, setError: setErrorMessage };
}
