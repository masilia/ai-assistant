<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ToolCall;
use Masilia\AiAssistant\Client\ToolCallResult;

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

    /**
     * Build a request body with tool definitions for the Anthropic Messages API.
     *
     * Anthropic uses top-level `tools` with `input_schema` (JSON Schema).
     * The `system` prompt is a top-level key, not a message.
     *
     * Internally we use OpenAI-format messages (`role: 'assistant'` with
     * `tool_calls: [...]` and standalone `role: 'tool'` messages with
     * `tool_call_id`). Anthropic requires a different shape:
     * - assistant messages embed tool calls as `content: [{type: 'tool_use', ...}]` blocks
     * - tool results are `role: 'user'` messages with `content: [{type: 'tool_result', ...}]` blocks
     *
     * This method translates OpenAI → Anthropic on the way out.
     */
    protected function buildAnthropicToolRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        array  $messages,
        array  $tools,
    ): array {
        $systemPrompt = '';
        $convertedMessages = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';

            if ($role === 'system') {
                $systemPrompt = $msg['content'] ?? '';
                continue;
            }

            if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                $content = [];
                $text = $msg['content'] ?? null;
                if ($text !== null && $text !== '') {
                    $content[] = ['type' => 'text', 'text' => $text];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    // Support both OpenAI-compatible nested format and legacy flat format.
                    // Nested: {id, type: "function", function: {name, arguments: "json_string"}}
                    // Flat:   {id, name, arguments: mixed}
                    $name = $tc['function']['name'] ?? $tc['name'] ?? '';
                    $rawArgs = $tc['function']['arguments'] ?? $tc['arguments'] ?? [];
                    $input = is_string($rawArgs) ? (json_decode($rawArgs, true) ?? []) : $rawArgs;

                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $tc['id'] ?? '',
                        'name' => $name,
                        'input' => $input,
                    ];
                }
                $convertedMessages[] = ['role' => 'assistant', 'content' => $content];
                continue;
            }

            if ($role === 'tool') {
                $convertedMessages[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => $msg['tool_call_id'] ?? '',
                        'content' => $msg['content'] ?? '',
                    ]],
                ];
                continue;
            }

            $convertedMessages[] = $msg;
        }

        $body = [
            'model' => $modelIdentifier,
            'temperature' => $this->clampAnthropicTemperature($temperature),
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $convertedMessages,
        ];

        if ($tools !== []) {
            $body['tools'] = array_map(static fn (array $tool): array => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['parameters'],
            ], $tools);
        }

        return $body;
    }

    /**
     * Parse an Anthropic Messages API response into a ToolCallResult.
     *
     * Anthropic returns content as an array of blocks:
     *   - {type: "text", text: "..."}
     *   - {type: "tool_use", id: "...", name: "...", input: {...}}
     *
     * Both can appear in the same response.
     */
    protected function parseAnthropicToolResponse(array $data): ToolCallResult
    {
        $text = null;
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $blockText = trim($block['text'] ?? '');
                if ($blockText !== '') {
                    $text = $block !== '' ? ($text ?? '') . $blockText : $blockText;
                }
            } elseif ($type === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'] ?? '',
                    name: $block['name'] ?? '',
                    arguments: $block['input'] ?? [],
                );
            }
        }

        return new ToolCallResult(
            text: $text !== '' ? $text : null,
            toolCalls: $toolCalls,
        );
    }

    private function clampAnthropicTemperature(float $temperature): float
    {
        if ($temperature < 0.01) {
            return 0.01;
        }
        if ($temperature > 1.0) {
            return 1.0;
        }

        return $temperature;
    }
}
