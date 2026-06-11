<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * Base adapter for providers that follow the OpenAI-compatible chat completions API
 * (OpenAI, Mistral, Ollama, and any custom OpenAI-compatible endpoints).
 *
 * Subclasses only need to define their provider identifier, default base URL,
 * and default test model. All request/response logic is shared.
 */
abstract class AbstractOpenAiAdapter implements ProviderAdapterInterface, StreamingProviderAdapterInterface, TestableProviderAdapterInterface
{
    use EndpointUrlHelperTrait;

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === $this->getProviderIdentifier();
    }

    abstract protected function getProviderIdentifier(): string;

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $host = self::extractHost($customApiUrl ?: $this->getDefaultHost());

        return $host . $this->getChatEndpointPath();
    }

    abstract protected function getDefaultHost(): string;

    protected function getChatEndpointPath(): string
    {
        return '/v1/chat/completions';
    }

    public function buildHeaders(?string $apiKey): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return $headers;
    }

    public function parseResponse(array $data): string
    {
        $text = trim($data['choices'][0]['message']['content'] ?? '');

        if ($text === '') {
            throw new \RuntimeException(
                sprintf(
                    '%s API returned an empty response. Raw: %s',
                    ProviderId::displayName($this->getProviderIdentifier()),
                    json_encode($data)
                )
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
        // OpenAI-compatible APIs (OpenAI, Qwen, Mistral, Ollama) require
        // this flag for the final SSE chunk to include a `usage` block.
        $body['stream_options'] = ['include_usage' => true];

        return $body;
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
            'temperature' => $this->getLimits()->clampTemperature($temperature),
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    public function getLimits(): \Masilia\AiAssistant\Client\ProviderLimits
    {
        return \Masilia\AiAssistant\Client\ProviderLimits::openAiCompatible();
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

    public function extractUsage(array $data): ?array
    {
        $usage = $data['usage'] ?? null;
        if (!is_array($usage)) {
            return null;
        }

        return [
            'input' => isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : null,
            'output' => isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : null,
            'finishReason' => isset($data['choices'][0]['finish_reason']) ? (string)$data['choices'][0]['finish_reason'] : null,
        ];
    }

    public function extractStreamUsage(array $lastChunk, ?string $lastFinishReason = null): ?array
    {
        $usage = $lastChunk['usage'] ?? null;
        $finish = $lastChunk['choices'][0]['finish_reason'] ?? $lastFinishReason;

        if (!is_array($usage) && $finish === null) {
            return null;
        }

        return [
            'input' => isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : null,
            'output' => isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : null,
            'finishReason' => $finish,
        ];
    }
}
