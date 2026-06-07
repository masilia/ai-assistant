<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Base adapter for providers that follow the OpenAI-compatible chat completions API
 * (OpenAI, Mistral, Ollama, and any custom OpenAI-compatible endpoints).
 *
 * Subclasses only need to define their provider identifier, default base URL,
 * and default test model. All request/response logic is shared.
 */
abstract class AbstractOpenAiAdapter implements ProviderAdapterInterface
{
    abstract protected function getProviderIdentifier(): string;

    abstract protected function getDefaultBaseUrl(): string;

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === $this->getProviderIdentifier();
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $base = rtrim($customApiUrl ?: $this->getDefaultBaseUrl(), '/');

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
                sprintf('%s API returned an empty response. Raw: %s', ucfirst($this->getProviderIdentifier()), json_encode($data))
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

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

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
