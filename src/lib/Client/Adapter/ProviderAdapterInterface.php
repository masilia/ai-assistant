<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderLimits;

/**
 * Base provider adapter contract. Every AI provider adapter must
 * implement at least this interface. Streaming and testable providers
 * additionally implement {@see StreamingProviderAdapterInterface} and
 * {@see TestableProviderAdapterInterface}.
 */
interface ProviderAdapterInterface
{
    public function supports(string $providerIdentifier): bool;

    public function buildEndpointUrl(?string $customApiUrl): string;

    public function buildHeaders(?string $apiKey): array;

    public function buildRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array;

    public function parseResponse(array $data): string;

    /**
     * Optional: extract token usage from the response body.
     * Returns {input: int, output: int, finishReason: ?string}
     * or null if the adapter can't determine usage.
     */
    public function extractUsage(array $data): ?array;

    /**
     * Per-provider shape limits. Default: OpenAI-compatible.
     * Adapters with quirks (Anthropic, MiniMax) override.
     */
    public function getLimits(): ProviderLimits;
}
