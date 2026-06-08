<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Masilia\AiAssistant\Client\Adapter\TestableProviderAdapterInterface;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Computes the 3-state health of the AI engine for the current siteaccess:
 *   - not_configured: no active provider in DB
 *   - online:        active provider is reachable
 *   - offline:       active provider configured but unreachable / test failed
 *
 * Backed by a thin HTTP probe (using the same adapter as the
 * ProviderConnectionTester). The result is cheap enough to call on
 * every dashboard load.
 */
readonly class HealthChecker
{
    public function __construct(
        private AiProviderRepositoryInterface $providerRepository,
        private ProviderAdapterRegistry       $adapterRegistry,
        private HttpClientInterface           $httpClient,
    ) {
    }

    /**
     * @return array{
     *   state: 'not_configured'|'online'|'offline',
     *   providerId: int|null,
     *   providerName: string|null,
     *   message: string|null,
     *   checkedAt: string,
     * }
     */
    public function check(): array
    {
        $resolved = $this->providerRepository->findActive();

        if ($resolved === null) {
            return [
                'state' => 'not_configured',
                'providerId' => null,
                'providerName' => null,
                'message' => null,
                'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        $adapter = $this->adapterRegistry->getForProvider($resolved->providerIdentifier);

        if (!$adapter instanceof TestableProviderAdapterInterface) {
            // Provider is configured but the adapter can't be tested; treat
            // it as online (we can't prove otherwise).
            return [
                'state' => 'online',
                'providerId' => null,
                'providerName' => $resolved->name,
                'message' => 'Adapter does not support connection testing.',
                'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        $body = $adapter->buildTestRequestBody($resolved->modelIdentifier);
        $url  = $adapter->buildEndpointUrl($resolved->apiUrl);
        $headers = $adapter->buildHeaders($resolved->apiKey);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
        } catch (\Throwable $e) {
            return [
                'state' => 'offline',
                'providerId' => null,
                'providerName' => $resolved->name,
                'message' => $e->getMessage(),
                'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        if ($status === 200) {
            return [
                'state' => 'online',
                'providerId' => null,
                'providerName' => $resolved->name,
                'message' => null,
                'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        return [
            'state' => 'offline',
            'providerId' => null,
            'providerName' => $resolved->name,
            'message' => sprintf('HTTP %d', $status),
            'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
