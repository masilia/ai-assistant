<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

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
}
