<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * MiniMax uses the Anthropic Messages API format but with its own
 * X-Api-Key auth header and a different default base URL.
 *
 * No inheritance from AnthropicAdapter: the two classes genuinely
 * share only the response-parsing logic, which lives in
 * {@see AnthropicMessagesResponseTrait}. Everything else (endpoint
 * URL, auth header, request body shape quirks, test body) is
 * adapter-specific.
 */
class MiniMaxAdapter implements ProviderAdapterInterface, StreamingProviderAdapterInterface, TestableProviderAdapterInterface
{
    use AnthropicMessagesResponseTrait;

    private const DEFAULT_BASE_URL = 'https://api.minimax.io/anthropic/v1';
    private const DEFAULT_TEST_MODEL = 'MiniMax-M2.5';

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::MINIMAX;
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $base = rtrim($customApiUrl ?: self::DEFAULT_BASE_URL, '/');

        if (!str_ends_with($base, '/messages')) {
            $base .= '/messages';
        }

        return $base;
    }

    public function buildHeaders(?string $apiKey): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($apiKey)) {
            $headers['X-Api-Key'] = $apiKey;
        }

        return $headers;
    }

    public function buildRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        return [
            'model' => $modelIdentifier,
            'temperature' => $this->getLimits()->clampTemperature($temperature),
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    public function parseResponse(array $data): string
    {
        $text = $this->extractTextBlock($data);

        if ($text === '') {
            throw new \RuntimeException(
                sprintf('MiniMax API returned an empty response. Raw: %s', json_encode($data))
            );
        }

        return $text;
    }

    public function buildTestRequestBody(string $modelIdentifier): array
    {
        return [
            'model' => $modelIdentifier,
            'max_tokens' => 10,
            'temperature' => 0.7,
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
    ): array {
        $body = $this->buildRequestBody($modelIdentifier, $temperature, $maxTokens, $systemPrompt, $userPrompt);
        $body['stream'] = true;

        return $body;
    }

    public function parseStreamChunk(string $line): ?string
    {
        $trimmed = trim($line);

        if (!str_starts_with($trimmed, 'event: ')) {
            if (str_starts_with($trimmed, 'data: ')) {
                $json = trim(substr($trimmed, 6));
                if ($json === '' || $json === '{}') {
                    return null;
                }
                $data = json_decode($json, true);
                if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                    return $data['delta']['text'] ?? null;
                }
            }
            return null;
        }

        return null;
    }

    public function isStreamEnd(string $line): bool
    {
        $trimmed = trim($line);
        return str_starts_with($trimmed, 'event: message_stop');
    }

    public function getLimits(): \Masilia\AiAssistant\Client\ProviderLimits
    {
        return \Masilia\AiAssistant\Client\ProviderLimits::anthropicMessages(self::DEFAULT_TEST_MODEL);
    }

    public function extractUsage(array $data): ?array
    {
        // MiniMax uses the same Anthropic Messages response shape.
        $usage = $data['usage'] ?? null;
        if (!is_array($usage)) {
            return null;
        }

        return [
            'input'        => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output'       => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'finishReason' => isset($data['stop_reason']) ? (string) $data['stop_reason'] : null,
        ];
    }
}
