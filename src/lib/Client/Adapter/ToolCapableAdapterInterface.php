<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ToolCallResult;

/**
 * Opt-in interface for adapters that support native function-calling / tool-use.
 *
 * Adapters implementing this interface can build request bodies with tool
 * definitions and parse tool-call responses from the provider.
 *
 * Adapters that don't implement this interface (e.g. a future custom adapter
 * that only does plain chat) can still implement just {@see ProviderAdapterInterface}.
 * The {@see \Masilia\AiAssistant\Client\AiClient::chatWithTools()} method
 * will fall back to a text-based action protocol in that case.
 */
interface ToolCapableAdapterInterface extends ProviderAdapterInterface
{
    /**
     * Build a request body that includes tool definitions.
     *
     * @param array  $messages Conversation messages in provider-native format:
     *                         [{role: 'system'|'user'|'assistant'|'tool', content: ...}]
     * @param array  $tools    Tool definitions:
     *                         [{name: string, description: string, parameters: array}]
     */
    public function buildToolRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        array  $messages,
        array  $tools,
    ): array;

    /**
     * Parse a tool-capable response into either text or tool calls.
     *
     * The returned {@see ToolCallResult} may contain:
     * - text only (LLM decided not to call any tools)
     * - tool calls only (LLM wants to call tools)
     * - both text and tool calls (LLM provided context + tool calls)
     */
    public function parseToolResponse(array $data): ToolCallResult;
}
