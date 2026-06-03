<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * MiniMax uses the Anthropic Messages API format but with its own auth header.
 * Extends AnthropicAdapter to reuse body building and response parsing logic.
 */
class MiniMaxAdapter extends AnthropicAdapter
{
    private const DEFAULT_BASE_URL = 'https://api.minimax.io/anthropic/v1';
    private const DEFAULT_TEST_MODEL = 'MiniMax-M2.5';

    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === 'minimax';
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
    ): array
    {
        // MiniMax requires temperature strictly > 0; clamp 0 → 0.01.
        $body = parent::buildRequestBody($modelIdentifier, max(0.01, $temperature), $maxTokens, $systemPrompt, $userPrompt);

        return $body;
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
}
