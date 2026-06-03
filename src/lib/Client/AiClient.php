<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiModelRepositoryInterface;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider-agnostic AI client.
 *
 * Resolves the active provider/model from the database and delegates request
 * building and response parsing to the matching {@see ProviderAdapterInterface}.
 * When no provider is configured in the database, it falls back to an
 * environment-configured OpenAI provider routed through the same adapter system.
 */
class AiClient implements AiClientInterface
{
    private const FALLBACK_PROVIDER_IDENTIFIER = 'openai';

    public function __construct(
        private readonly HttpClientInterface              $httpClient,
        private readonly AiProviderRepositoryInterface    $providerRepository,
        private readonly AiModelRepositoryInterface       $modelRepository,
        private readonly ProviderAdapterRegistry          $adapterRegistry,
        private readonly ?string                          $apiKey,
        private readonly string                           $model = 'gpt-4o',
        private readonly float                            $temperature = 0.7,
        private readonly int                              $maxTokens = 2048,
    ) {
    }

    public function suggest(string $systemPrompt, string $userPrompt): string
    {
        $target = $this->resolveTarget();

        $body = $target->adapter->buildRequestBody(
            $target->modelIdentifier,
            $target->temperature,
            $target->maxTokens,
            $systemPrompt,
            $userPrompt,
        );

        $response = $this->httpClient->request('POST', $target->url, [
            'headers' => $target->headers,
            'json' => $body,
        ]);

        $this->assertOk($response, $target->providerIdentifier);

        return $target->adapter->parseResponse($response->toArray());
    }

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator
    {
        $target = $this->resolveTarget();

        $body = $target->adapter->buildStreamRequestBody(
            $target->modelIdentifier,
            $target->temperature,
            $target->maxTokens,
            $systemPrompt,
            $userPrompt,
        );

        $response = $this->httpClient->request('POST', $target->url, [
            'headers' => $target->headers,
            'json' => $body,
            'buffer' => false,
        ]);

        $this->assertOk($response, $target->providerIdentifier);

        yield from $this->consumeStream($response, $target->adapter);
    }

    /**
     * Resolves the active provider/model (or the env fallback) into a single
     * value object carrying everything needed to perform a request.
     */
    private function resolveTarget(): AiTarget
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

            return new AiTarget(
                adapter: $adapter,
                providerIdentifier: $activeProvider->getIdentifier(),
                modelIdentifier: $activeModel->getIdentifier(),
                temperature: $activeModel->getTemperature(),
                maxTokens: $activeModel->getMaxTokens(),
                url: $adapter->buildEndpointUrl($activeProvider->getApiUrl()),
                headers: $adapter->buildHeaders($activeProvider->getApiKey()),
            );
        }

        return $this->buildFallbackTarget();
    }

    /**
     * Builds an env-configured OpenAI target routed through the OpenAI adapter,
     * so the fallback shares the exact same request/parse logic as DB providers.
     */
    private function buildFallbackTarget(): AiTarget
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException(
                'No active AI provider is configured and no fallback OpenAI API key is set '
                . '(masilia_ai_assistant.openai.api_key).'
            );
        }

        $adapter = $this->adapterRegistry->getForProvider(self::FALLBACK_PROVIDER_IDENTIFIER);

        return new AiTarget(
            adapter: $adapter,
            providerIdentifier: self::FALLBACK_PROVIDER_IDENTIFIER,
            modelIdentifier: $this->model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            url: $adapter->buildEndpointUrl(null),
            headers: $adapter->buildHeaders($this->apiKey),
        );
    }

    private function assertOk(ResponseInterface $response, string $providerIdentifier): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf(
                    '%s API error (HTTP %d): %s',
                    ucfirst($providerIdentifier),
                    $statusCode,
                    $response->getContent(false)
                )
            );
        }
    }

    /**
     * Consumes a Server-Sent Events stream line-by-line using the adapter to
     * extract tokens. Uses the Symfony HttpClient streaming API.
     *
     * @return \Generator<int, string>
     */
    private function consumeStream(ResponseInterface $response, ProviderAdapterInterface $adapter): \Generator
    {
        $buffer = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isLast()) {
                break;
            }

            $buffer .= $chunk->getContent();

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line === '') {
                    continue;
                }

                if ($adapter->isStreamEnd($line)) {
                    return;
                }

                $token = $adapter->parseStreamChunk($line);
                if ($token !== null) {
                    yield $token;
                }
            }
        }

        $line = trim($buffer);
        if ($line !== '' && !$adapter->isStreamEnd($line)) {
            $token = $adapter->parseStreamChunk($line);
            if ($token !== null) {
                yield $token;
            }
        }
    }
}
