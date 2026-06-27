import { useRef, useCallback } from 'react';

/**
 * Shared SSE streaming primitive.
 *
 * Owns the low-level protocol layer:
 *   fetch → ReadableStream reader → TextDecoder (with proper multi-byte
 *   UTF-8 tail flush) → line buffer → JSON parse → onEvent callback.
 *
 * Both the AI Suggest Modal (useAiStream) and the Agent Chat Widget use
 * this hook so that bug-fixes and protocol changes are applied once.
 *
 * The hook is intentionally stateless — all reactive state lives in the
 * calling hook or component. This keeps the primitive reusable and testable.
 *
 * @param {{
 *   onEvent:  (event: object) => void,   // called for every parsed JSON event
 *   onDone:   () => void,                // called when stream ends cleanly ([DONE] or reader done)
 *   onError:  (message: string) => void, // called on network / parse error
 * }} callbacks
 *
 * @returns {{
 *   stream:  (url: string, fetchOptions: RequestInit) => Promise<void>,
 *   cancel:  () => void,
 * }}
 */
export function useSSEStream({ onEvent, onDone, onError }) {
    const abortRef = useRef(null);

    const cancel = useCallback(() => {
        if (abortRef.current) {
            abortRef.current.abort();
            abortRef.current = null;
        }
    }, []);

    const stream = useCallback(async (url, fetchOptions = {}) => {
        cancel();

        const controller = new AbortController();
        abortRef.current = controller;

        try {
            const res = await fetch(url, {
                ...fetchOptions,
                signal: controller.signal,
            });

            if (!res.ok) {
                let msg = `HTTP ${res.status}`;
                try {
                    const data = await res.json();
                    msg = data.error?.message || (typeof data.error === 'string' ? data.error : msg);
                } catch {
                    // response body not JSON, keep generic message
                }
                onError(msg);
                return;
            }

            const reader = res.body.getReader();
            // Use a single decoder instance so multi-byte UTF-8 sequences that
            // span two chunks are reconstructed correctly. The final decode()
            // call (no arguments) flushes any bytes still held in the codec's
            // internal buffer — this fixes the silent character-drop bug that
            // affected Arabic and French content in the agent chat.
            const decoder = new TextDecoder();
            let buffer = '';

            const processLines = (text) => {
                if (!text) return;
                const lines = text.split('\n');
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed.startsWith('data: ')) continue;

                    const payload = trimmed.slice(6);
                    if (!payload) continue;

                    if (payload === '[DONE]') {
                        onDone();
                        return;
                    }

                    try {
                        const event = JSON.parse(payload);
                        onEvent(event);
                    } catch {
                        // Skip malformed JSON lines
                    }
                }
            };

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    // Flush any bytes still held in the decoder (multi-byte
                    // UTF-8 sequences split across the last two chunks).
                    buffer += decoder.decode();
                    processLines(buffer);
                    onDone();
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';
                processLines(lines.join('\n'));
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            onError(err.message || 'Network error');
        } finally {
            abortRef.current = null;
        }
    }, [onEvent, onDone, onError, cancel]);

    return { stream, cancel };
}
