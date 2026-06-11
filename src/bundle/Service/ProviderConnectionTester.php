<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Client\Adapter\StreamingProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\TestableProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests reachability of an AI provider by issuing a minimal request
 * through its adapter. Returns a structured result for the API response.
 *
 * When called with $testStream = true, the test additionally exercises
 * the SSE / streaming code path: it sets stream=true on the request
 * body and validates that the first streamed chunk arrives. This
 * catches the common "non-streaming works but streaming is broken"
 * failure mode (wrong endpoint suffix, missing stream flag, etc.).
 */
readonly class ProviderConnectionTester
{
    private const STREAM_TEST_TEMPERATURE = 0.7;
    private const STREAM_TEST_MAX_TOKENS = 10;
    private const STREAM_TEST_SYSTEM = 'ping';
    private const STREAM_TEST_USER = 'hi';

    public function __construct(
        private AiProviderRepository    $providerRepository,
        private ProviderAdapterRegistry $adapterRegistry,
        private HttpClientInterface     $httpClient,
    ) {
    }

    /**
     * @return array{success: bool, message: string, httpStatus: int|null, streamTested: bool, streamOk: bool|null}
     */
    public function test(int $providerId, bool $testStream = false): array
    {
        $provider = $this->providerRepository->find($providerId)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $adapter = $this->adapterRegistry->getForProvider($provider->getIdentifier());

        if (!$adapter instanceof TestableProviderAdapterInterface) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Provider adapter "%s" does not implement connection-test support.',
                    $adapter::class
                ),
                'httpStatus' => null,
                'streamTested' => false,
                'streamOk' => null,
            ];
        }

        $models = $provider->getModels();
        $testModel = $models->count() > 0
            ? $models->first()->getIdentifier()
            : $adapter->getDefaultTestModel();

        $url = $adapter->buildEndpointUrl($provider->getApiUrl());
        $headers = $adapter->buildHeaders($provider->getApiKey());
        $body = $adapter->buildTestRequestBody($testModel);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'json' => $body,
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            return [
                'success' => false,
                'message' => sprintf('API returned HTTP %d: %s', $statusCode, $response->getContent(false)),
                'httpStatus' => $statusCode,
                'streamTested' => false,
                'streamOk' => null,
            ];
        }

        // Sync test passed. Optionally exercise the streaming path.
        $streamOk = null;
        if ($testStream) {
            $streamOk = $this->testStream($provider, $this->adapterRegistry, $testModel, $url, $headers);
        }

        $message = $streamOk === false
            ? 'Connection works but streaming is broken.'
            : ($testStream ? 'Connection successful! Streaming also works.' : 'Connection successful!');

        return [
            'success' => $streamOk !== false,
            'message' => $message,
            'httpStatus' => $statusCode,
            'streamTested' => $testStream,
            'streamOk' => $streamOk,
        ];
    }

    private function testStream(
        AiProvider $provider,
        ProviderAdapterRegistry $registry,
        string $testModel,
        string $url,
        array $headers,
    ): bool {
        $adapter = $registry->getForProvider($provider->getIdentifier());

        if (!$adapter instanceof StreamingProviderAdapterInterface) {
            // Adapter is non-streaming: trivially 'no streaming to test'.
            return true;
        }

        $body = $adapter->buildStreamRequestBody(
            $testModel,
            self::STREAM_TEST_TEMPERATURE,
            self::STREAM_TEST_MAX_TOKENS,
            self::STREAM_TEST_SYSTEM,
            self::STREAM_TEST_USER,
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
                'buffer' => false,
                'timeout' => 30,
            ]);

            // Read up to 2 chunks, return true as soon as we get a real token.
            $count = 0;
            $sawToken = false;
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isLast()) break;
                $count++;
                $content = $chunk->getContent();
                if ($content !== '' && $content !== false) {
                    // Look for at least one valid SSE line
                    if (str_contains($content, 'data:') || str_contains($content, 'event:')) {
                        $sawToken = true;
                        break;
                    }
                }
                if ($count >= 2) break;
            }

            return $sawToken;
        } catch (\Throwable) {
            return false;
        }
    }
}
