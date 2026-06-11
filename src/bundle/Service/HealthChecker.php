<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Masilia\AiAssistant\Client\Adapter\TestableProviderAdapterInterface;
use Masilia\Bundle\AiAssistant\Health\HealthReport;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Computes the 3-state health of the AI engine for the current siteaccess:
 *   - not_configured: no active provider in DB
 *   - online:        active provider is reachable (or the adapter can't be tested)
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

    public function check(): HealthReport
    {
        $resolved = $this->providerRepository->findActive();

        if ($resolved === null) {
            return HealthReport::notConfigured();
        }

        $adapter = $this->adapterRegistry->getForProvider($resolved->providerIdentifier);

        if (!$adapter instanceof TestableProviderAdapterInterface) {
            // Provider is configured but the adapter can't be tested; treat
            // it as online (we can't prove otherwise).
            return HealthReport::online($resolved->name, 'Adapter does not support connection testing.');
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
            return HealthReport::offline($resolved->name, $e->getMessage());
        }

        if ($status === 200) {
            return HealthReport::online($resolved->name);
        }

        return HealthReport::offline($resolved->name, sprintf('HTTP %d', $status));
    }
}
