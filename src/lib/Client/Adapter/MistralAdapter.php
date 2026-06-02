<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class MistralAdapter implements ProviderAdapterInterface
{
    private const DEFAULT_BASE_URL = 'https://api.mistral.ai/v1';
    private const DEFAULT_TEST_MODEL = 'mistral-small-latest';

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === 'mistral';
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $base = rtrim($customApiUrl ?: self::DEFAULT_BASE_URL, '/');

        if (!str_ends_with($base, '/chat/completions')) {
            $base .= '/chat/completions';
        }

        return $base;
    }

    public function buildHeaders(?string $apiKey): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return $headers;
    }

    public function buildRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array
    {
        return [
            'model' => $modelIdentifier,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    public function parseResponse(array $data): string
    {
        $text = trim($data['choices'][0]['message']['content'] ?? '');

        if ($text === '') {
            throw new \RuntimeException(
                sprintf('Mistral API returned an empty response. Raw: %s', json_encode($data))
            );
        }

        return $text;
    }

    public function buildTestRequestBody(string $modelIdentifier): array
    {
        return [
            'model' => $modelIdentifier,
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'Say hello']],
        ];
    }

    public function getDefaultTestModel(): string
    {
        return self::DEFAULT_TEST_MODEL;
    }

    public function buildStreamRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array
    {
        $body = $this->buildRequestBody($modelIdentifier, $temperature, $maxTokens, $systemPrompt, $userPrompt);
        $body['stream'] = true;
        return $body;
    }

    public function parseStreamChunk(string $line): ?string
    {
        if (!str_starts_with($line, 'data: ')) {
            return null;
        }

        $json = trim(substr($line, 6));

        if ($json === '[DONE]' || $json === 'DONE') {
            return null;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return null;
        }

        return $data['choices'][0]['delta']['content'] ?? null;
    }

    public function isStreamEnd(string $line): bool
    {
        $trimmed = trim($line);
        return $trimmed === 'data: [DONE]' || $trimmed === '[DONE]' || $trimmed === 'DONE';
    }
}
