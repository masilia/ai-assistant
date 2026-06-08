<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class AnthropicAdapter implements ProviderAdapterInterface, StreamingProviderAdapterInterface, TestableProviderAdapterInterface
{
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';
    private const DEFAULT_TEST_MODEL = 'claude-sonnet-4-5';

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::ANTHROPIC;
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
        $headers = [
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];

        if (!empty($apiKey)) {
            $headers['x-api-key'] = $apiKey;
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
                sprintf('Anthropic API returned an empty response. Raw: %s', json_encode($data))
            );
        }

        return $text;
    }

    /**
     * Anthropic may return multiple content blocks (e.g. thinking + text).
     * Find the first block with type='text'.
     */
    protected function extractTextBlock(array $data): string
    {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return trim($block['text'] ?? '');
            }
        }

        return '';
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
        return [
            'model' => $modelIdentifier,
            'temperature' => max(0.01, $temperature),
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'stream' => true,
        ];
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
}
