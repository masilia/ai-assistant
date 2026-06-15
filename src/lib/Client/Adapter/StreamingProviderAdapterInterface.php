<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Opt-in streaming support. Adapters that implement this interface
 * can be used by the streaming endpoint
 * ({@see \\Masilia\\AiAssistant\\Client\\AiClient::suggestStream()}).
 *
 * Adapters that don't (e.g. a future custom adapter that only does
 * non-streaming) can still implement just {@see ProviderAdapterInterface}
 * and be usable for the sync endpoint.
 */
interface StreamingProviderAdapterInterface extends ProviderAdapterInterface
{
    public function buildStreamRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array;

    public function parseStreamChunk(string $line): ?string;

    public function isStreamEnd(string $line): bool;

    /**
     * Extract the finish reason from a decoded SSE chunk.
     *
     * Each provider uses different JSON keys:
     * - OpenAI: `choices[0].finish_reason`
     * - Anthropic: `delta.stop_reason` or `stop_reason`
     * - MiniMax: `stop_reason`
     */
    public function extractFinishReason(array $data): ?string;

    /**
     * Inspect the last decoded SSE chunk and the last seen finish-reason
     * (already extracted by {@see StreamConsumer}) and return usage data
     * in the same shape as {@see ProviderAdapterInterface::extractUsage()}:
     *
     *   ['input' => int|null, 'output' => int|null, 'finishReason' => string|null]
     *
     * Returns null if the stream did not surface a usage block (some
     * providers do not return token counts over SSE).
     */
    public function extractStreamUsage(array $lastChunk, ?string $lastFinishReason = null): ?array;
}
