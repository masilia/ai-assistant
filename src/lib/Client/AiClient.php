<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\StreamingProviderAdapterInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

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
    private const UNKNOWN_PROVIDER = 'unknown';

    private RequestLoggerInterface $requestLogger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TargetResolver      $resolver,
        private readonly StreamConsumer      $streamConsumer,
        ?RequestLoggerInterface              $requestLogger = null,
    )
    {
        $this->requestLogger = $requestLogger ?? new NullRequestLogger();
    }

    public function suggest(string $systemPrompt, string $userPrompt): string
    {
        $start = microtime(true);

        try {
            $target = $this->resolver->resolve();
        } catch (Throwable $e) {
            $this->logResolutionFailure($start, $e);
            throw $e;
        }

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
        } catch (Throwable $e) {
            $this->logFailure($target, $start, $e);
            throw $e;
        }
    }

    /**
     * Logs a failure that happened BEFORE we had an AiTarget (i.e. during
     * target resolution). The provider/model are unknown, but the failure
     * is still worth recording so the Usage tab can show config errors.
     */
    private function logResolutionFailure(float $startMs, Throwable $e): void
    {
        $this->requestLogger->log([
            'providerIdentifier' => self::UNKNOWN_PROVIDER,
            'modelIdentifier' => self::UNKNOWN_PROVIDER,
            'success' => false,
            'latencyMs' => $this->elapsedMs($startMs),
            'errorCode' => $this->extractErrorCode($e),
            'tokensIn' => null,
            'tokensOut' => null,
            'siteaccess' => null,
            'finishReason' => null
        ]);
    }

    private function elapsedMs(float $start): int
    {
        return (int)round((microtime(true) - $start) * 1000);
    }

    private function extractErrorCode(Throwable $e): string
    {
        if ($e instanceof HttpRequestException) {
            return 'HTTP_' . $e->statusCode;
        }

        $class = $e::class;
        $shortName = strrchr($class, '\\');

        return $shortName !== false ? substr($shortName, 1) : $class;
    }

    private function assertOk(ResponseInterface $response, string $providerIdentifier): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new HttpRequestException(
                ProviderId::displayName($providerIdentifier),
                $statusCode,
                $response->getContent(false),
            );
        }
    }

    private function logSuccess(AiTarget $target, float $startMs, ?array $usage = null): void
    {
        $this->requestLogger->log([
            'providerIdentifier' => $target->providerIdentifier,
            'modelIdentifier' => $target->modelIdentifier,
            'success' => true,
            'latencyMs' => $this->elapsedMs($startMs),
            'errorCode' => null,
            'tokensIn' => $usage['input'] ?? 0,
            'tokensOut' => $usage['output'] ?? 0,
            'finishReason' => $usage['finishReason'] ?? null,
            'siteaccess' => $target->siteaccess,
        ]);
    }

    private function logFailure(AiTarget $target, float $startMs, Throwable $e): void
    {
        $this->requestLogger->log([
            'providerIdentifier' => $target->providerIdentifier,
            'modelIdentifier' => $target->modelIdentifier,
            'success' => false,
            'latencyMs' => $this->elapsedMs($startMs),
            'errorCode' => $this->extractErrorCode($e),
            'tokensIn' => null,
            'tokensOut' => null,
            'siteaccess' => $target->siteaccess,
            'finishReason' => null
        ]);
    }

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator
    {
        $start = microtime(true);

        try {
            $target = $this->resolver->resolve();
        } catch (Throwable $e) {
            $this->logResolutionFailure($start, $e);
            throw $e;
        }

        if (!$target->adapter instanceof StreamingProviderAdapterInterface) {
            $e = new RuntimeException(sprintf(
                'Provider "%s" does not support streaming (adapter %s).',
                $target->providerIdentifier,
                $target->adapter::class
            ));
            $this->logFailure($target, $start, $e);
            throw $e;
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
        } catch (Throwable $e) {
            $this->logFailure($target, $start, $e);
            throw $e;
        }

        // Wrap the generator so mid-stream failures are also logged.
        // The connection established successfully (we passed assertOk);
        // we delay logging success until the entire stream has been
        // consumed, so a mid-stream drop or parse error flips the
        // outcome to failure. The final event carries the usage data
        // extracted from the last SSE chunk, which the logger persists.
        $consumer = $this->streamConsumer;

        return (function () use ($target, $start, $consumer, $response) {
            try {
                $usage = null;
                foreach ($consumer->consume($response, $target->adapter) as $event) {
                    if ($event->isFinal) {
                        $usage = $event->usage;
                        continue;
                    }
                    yield $event->token;
                }
                $this->logSuccess($target, $start, $usage);
            } catch (Throwable $e) {
                $this->logFailure($target, $start, $e);
                throw $e;
            }
        })();
    }
}
