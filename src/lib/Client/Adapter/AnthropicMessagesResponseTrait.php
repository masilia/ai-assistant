<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Shared logic for Anthropic-Messages-API-shaped adapters.
 *
 * Anthropic and MiniMax both speak the Anthropic Messages API but
 * have different auth headers and default URLs. The one method they
 * genuinely share is parsing the response body (looking for a
 * 'text' content block in the 'content' array, ignoring 'thinking'
 * blocks, etc.). Everything else is overridden.
 *
 * Use as a trait so the per-adapter class can `implements` the right
 * interfaces without inheriting from a sibling.
 */
trait AnthropicMessagesResponseTrait
{
    /**
     * Anthropic may return multiple content blocks (e.g. thinking + text).
     * Find the first block with type='text'.
     */
    protected function extractTextBlock(array $data): string
    {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return trim($block['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * Anthropic-Messages-API-shaped streaming usage extraction.
     *
     * Anthropic sends usage in a `message_delta` event:
     *   event: message_delta
     *   data: {"type":"message_delta","delta":{"stop_reason":"end_turn"},
     *          "usage":{"input_tokens":100,"output_tokens":200}}
     *
     * Stop reason may also arrive in `message_stop` (event-only) or in
     * the `delta.stop_reason` of message_delta itself.
     */
    protected function extractAnthropicStreamUsage(array $lastChunk, ?string $lastFinishReason = null): ?array
    {
        $usage = $lastChunk['usage'] ?? null;
        $finish = $lastChunk['delta']['stop_reason']
            ?? $lastChunk['stop_reason']
            ?? $lastFinishReason;

        if (!is_array($usage) && $finish === null) {
            return null;
        }

        return [
            'input' => isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : null,
            'output' => isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : null,
            'finishReason' => $finish,
        ];
    }

    /**
     * Extract finish reason from Anthropic-Messages-API-shaped SSE chunk.
     */
    protected function extractAnthropicFinishReason(array $data): ?string
    {
        return isset($data['delta']['stop_reason'])
            ? (string) $data['delta']['stop_reason']
            : (isset($data['stop_reason']) ? (string) $data['stop_reason'] : null);
    }
}
