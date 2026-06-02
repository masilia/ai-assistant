<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiModelRepository;
use Masilia\AiAssistant\Repository\AiProviderRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiClient implements AiClientInterface
{
    private const FALLBACK_API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface     $httpClient,
        private readonly AiProviderRepository    $providerRepository,
        private readonly AiModelRepository       $modelRepository,
        private readonly ProviderAdapterRegistry $adapterRegistry,
        private readonly ?string                 $apiKey,
        private readonly string                  $model = 'gpt-4o',
        private readonly float                   $temperature = 0.7,
        private readonly int                     $maxTokens = 2048,
    )
    {
    }

    public function suggest(string $systemPrompt, string $userPrompt): string
    {
        $activeProvider = $this->providerRepository->findActive();

        if ($activeProvider !== null) {
            $activeModel = $this->modelRepository->findActiveForProvider($activeProvider)
                ?? $this->modelRepository->findActiveGlobal();

            if ($activeModel === null) {
                throw new \RuntimeException(
                    sprintf('AI Provider "%s" is active, but no active model is configured.', $activeProvider->getName())
                );
            }

            $adapter = $this->adapterRegistry->getForProvider($activeProvider->getIdentifier());
            $url = $adapter->buildEndpointUrl($activeProvider->getApiUrl());
            $headers = $adapter->buildHeaders($activeProvider->getApiKey());
            $body = $adapter->buildRequestBody(
                $activeModel->getIdentifier(),
                $activeModel->getTemperature(),
                $activeModel->getMaxTokens(),
                $systemPrompt,
                $userPrompt,
            );

            $response = $this->httpClient->request('POST', $url, ['headers' => $headers, 'json' => $body]);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new \RuntimeException(
                    sprintf(
                        '%s API error (HTTP %d): %s',
                        ucfirst($activeProvider->getIdentifier()),
                        $statusCode,
                        $response->getContent(false)
                    )
                );
            }

            return $adapter->parseResponse($response->toArray());
        }

        return $this->suggestWithFallback($systemPrompt, $userPrompt);
    }

    private function suggestWithFallback(string $systemPrompt, string $userPrompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Set the ai.openai.api_key parameter.');
        }

        $response = $this->httpClient->request('POST', self::FALLBACK_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf('OpenAI API error (HTTP %d): %s', $statusCode, $response->getContent(false))
            );
        }

        $data = $response->toArray();
        $text = trim($data['choices'][0]['message']['content'] ?? '');

        if ($text === '') {
            throw new \RuntimeException(
                sprintf('OpenAI API returned an empty response. Raw: %s', json_encode($data))
            );
        }

        return $text;
    }

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator
    {
        $activeProvider = $this->providerRepository->findActive();

        if ($activeProvider !== null) {
            $activeModel = $this->modelRepository->findActiveForProvider($activeProvider)
                ?? $this->modelRepository->findActiveGlobal();

            if ($activeModel === null) {
                throw new \RuntimeException(
                    sprintf('AI Provider "%s" is active, but no active model is configured.', $activeProvider->getName())
                );
            }

            $adapter = $this->adapterRegistry->getForProvider($activeProvider->getIdentifier());
            $url = $adapter->buildEndpointUrl($activeProvider->getApiUrl());
            $headers = $adapter->buildHeaders($activeProvider->getApiKey());
            $body = $adapter->buildStreamRequestBody(
                $activeModel->getIdentifier(),
                $activeModel->getTemperature(),
                $activeModel->getMaxTokens(),
                $systemPrompt,
                $userPrompt,
            );

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
                'buffer' => false,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \RuntimeException(
                    sprintf(
                        '%s API error (HTTP %d): %s',
                        ucfirst($activeProvider->getIdentifier()),
                        $statusCode,
                        $response->getContent(false)
                    )
                );
            }

            $buffer = '';
            foreach ($response->getContent() as $chunk) {
                $buffer .= $chunk;

                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);

                    $line = trim($line);

                    if ($adapter->isStreamEnd($line)) {
                        return;
                    }

                    $token = $adapter->parseStreamChunk($line);
                    if ($token !== null) {
                        yield $token;
                    }
                }
            }

            return;
        }

        yield from $this->streamWithFallback($systemPrompt, $userPrompt);
    }

    private function streamWithFallback(string $systemPrompt, string $userPrompt): \Generator
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Set the ai.openai.api_key parameter.');
        }

        $response = $this->httpClient->request('POST', self::FALLBACK_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
            'buffer' => false,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf('OpenAI API error (HTTP %d): %s', $statusCode, $response->getContent(false))
            );
        }

        $buffer = '';
        foreach ($response->getContent() as $chunk) {
            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                $line = trim($line);

                if ($line === 'data: [DONE]' || $line === '[DONE]' || $line === 'DONE') {
                    return;
                }

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = trim(substr($line, 6));
                $data = json_decode($json, true);

                if (!is_array($data)) {
                    continue;
                }

                $token = $data['choices'][0]['delta']['content'] ?? null;
                if ($token !== null) {
                    yield $token;
                }
            }
        }
    }
}
