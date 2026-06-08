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
}
