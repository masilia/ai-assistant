<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider-agnostic AI client. Thin orchestration layer that delegates:
 *   - target resolution (DB / YAML / env fallback) to {@see TargetResolver}
 *   - SSE line buffering                          to {@see StreamConsumer}
 *
 * Public API: {@see suggest()} (non-streaming) and {@see suggestStream()} (SSE).
 */
class AiClient implements AiClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TargetResolver      $resolver,
        private readonly StreamConsumer      $streamConsumer,
    ) {
    }

    public function suggest(string $systemPrompt, string $userPrompt): string
    {
        $target = $this->resolver->resolve();

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
        $target = $this->resolver->resolve();

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

        return $this->streamConsumer->consume($response, $target->adapter);
    }

    private function assertOk(ResponseInterface $response, string $providerIdentifier): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf(
                    '%s API error (HTTP %d): %s',
                    ProviderId::displayName($providerIdentifier),
                    $statusCode,
                    $response->getContent(false)
                )
            );
        }
    }
}
