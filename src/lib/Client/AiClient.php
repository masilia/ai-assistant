<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\StreamingProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider-agnostic AI client. Thin orchestration layer that delegates:
 *   - target resolution (DB / YAML / env fallback) to {@see TargetResolver}
 *   - SSE line buffering                          to {@see StreamConsumer}
 *   - request telemetry                           to {@see RequestLoggerInterface}
 *
 * Public API: {@see suggest()} (non-streaming) and {@see suggestStream()} (SSE).
 *
 * Telemetry: every call (success or failure, sync or stream) is
 * recorded via the request logger with provider/model/latency/error
 * data. No PII is ever logged (no field content, no API key).
 */
class AiClient implements AiClientInterface
{
    private RequestLoggerInterface $requestLogger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TargetResolver      $resolver,
        private readonly StreamConsumer      $streamConsumer,
        ?RequestLoggerInterface              $requestLogger = null,
    ) {
        $this->requestLogger = $requestLogger ?? new NullRequestLogger();
    }

    public function suggest(string $systemPrompt, string $userPrompt): string
    {
        $target = $this->resolver->resolve();
        $start = microtime(true);

        try {
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
            $rawData = $response->toArray();
            $result = $target->adapter->parseResponse($rawData);
            $usage = $target->adapter->extractUsage($rawData);

            $this->logSuccess($target, $start, $usage);

            return $result;
        } catch (\Throwable $e) {
            $this->logFailure($target, $start, $e);
            throw $e;
        }
    }

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator
    {
        $target = $this->resolver->resolve();
        $start = microtime(true);

        if (!$target->adapter instanceof StreamingProviderAdapterInterface) {
            throw new \RuntimeException(sprintf(
                'Provider "%s" does not support streaming (adapter %s).',
                $target->providerIdentifier,
                $target->adapter::class
            ));
        }

        try {
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

            // Log the success BEFORE yielding so we record that the
            // connection succeeded; token-level telemetry would be a
            // larger feature, not in scope here.
            $this->logSuccess($target, $start);

            return $this->streamConsumer->consume($response, $target->adapter);
        } catch (\Throwable $e) {
            $this->logFailure($target, $start, $e);
            throw $e;
        }
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

    private function logSuccess(AiTarget $target, float $startMs, ?array $usage = null): void
    {
        $this->requestLogger->log([
            'providerIdentifier' => $target->providerIdentifier,
            'modelIdentifier'    => $target->modelIdentifier,
            'success'             => true,
            'latencyMs'           => $this->elapsedMs($startMs),
            'errorCode'           => null,
            'tokensIn'            => $usage['input']  ?? null,
            'tokensOut'           => $usage['output'] ?? null,
            'finishReason'        => $usage['finishReason'] ?? null,
            'siteaccess'          => null,
        ]);
    }

    private function logFailure(AiTarget $target, float $startMs, \Throwable $e): void
    {
        $this->requestLogger->log([
            'providerIdentifier' => $target->providerIdentifier,
            'modelIdentifier'    => $target->modelIdentifier,
            'success'             => false,
            'latencyMs'           => $this->elapsedMs($startMs),
            'errorCode'           => $this->extractErrorCode($e),
            'tokensIn'            => null,
            'tokensOut'           => null,
            'siteaccess'          => null,
        ]);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function extractErrorCode(\Throwable $e): string
    {
        $message = $e->getMessage();
        // HTTP code in the message? Extract it. Otherwise return the
        // exception class short name (e.g. RuntimeException -> RuntimeException).
        if (preg_match('/HTTP (\d{3})/', $message, $m)) {
            return 'HTTP_' . $m[1];
        }
        $parts = explode('\\', $e::class);
        return end($parts) ?: 'UnknownError';
    }
}
