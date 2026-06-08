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
}
