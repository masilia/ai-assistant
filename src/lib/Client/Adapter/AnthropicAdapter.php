<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;
use Masilia\AiAssistant\Client\ProviderLimits;
use Masilia\AiAssistant\Client\ToolCallResult;

class AnthropicAdapter implements ProviderAdapterInterface, StreamingProviderAdapterInterface, TestableProviderAdapterInterface, ToolCapableAdapterInterface
{
    use AnthropicMessagesResponseTrait;
    use EndpointUrlHelperTrait;

    private const DEFAULT_HOST = 'https://api.anthropic.com';
    private const DEFAULT_TEST_MODEL = 'claude-sonnet-4-5';

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::ANTHROPIC;
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $host = self::extractHost($customApiUrl ?: self::DEFAULT_HOST);

        return $host . '/v1/messages';
    }

    public function buildHeaders(?string $apiKey): array
    {
        $headers = [
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];

        if (!empty($apiKey)) {
            $headers['x-api-key'] = $apiKey;
        }

        return $headers;
    }

    public function parseResponse(array $data): string
    {
        $text = $this->extractTextBlock($data);

        if ($text === '') {
            throw new \RuntimeException(
                sprintf('Anthropic API returned an empty response. Raw: %s', json_encode($data))
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
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    public function getLimits(): ProviderLimits
    {
        return ProviderLimits::anthropicMessages(self::DEFAULT_TEST_MODEL);
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

    public function extractUsage(array $data): ?array
    {
        $usage = $data['usage'] ?? null;
        if (!is_array($usage)) {
            return null;
        }

        return [
            'input' => isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : null,
            'output' => isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : null,
            'finishReason' => isset($data['stop_reason']) ? (string)$data['stop_reason'] : null,
        ];
    }

    public function extractStreamUsage(array $lastChunk, ?string $lastFinishReason = null): ?array
    {
        return $this->extractAnthropicStreamUsage($lastChunk, $lastFinishReason);
    }

    public function extractFinishReason(array $data): ?string
    {
        return $this->extractAnthropicFinishReason($data);
    }

    public function buildToolRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        array  $messages,
        array  $tools,
    ): array {
        return $this->buildAnthropicToolRequestBody(
            $modelIdentifier,
            $temperature,
            $maxTokens,
            $messages,
            $tools,
        );
    }

    public function parseToolResponse(array $data): ToolCallResult
    {
        return $this->parseAnthropicToolResponse($data);
    }
}
