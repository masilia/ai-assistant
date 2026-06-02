<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

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

    public function buildTestRequestBody(string $modelIdentifier): array;

    public function getDefaultTestModel(): string;

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
