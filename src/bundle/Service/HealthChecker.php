<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\TestableProviderAdapterInterface;
use Masilia\Bundle\AiAssistant\Health\HealthReport;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Client\Resolved\ResolvedProvider;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Computes the 3-state health of the AI engine for a configured provider:
 *   - not_configured: no active provider in DB
 *   - online:        configured provider is reachable (or the adapter can't be tested)
 *   - offline:       configured provider unreachable / test failed
 *
 * Backed by a thin HTTP probe (using the same adapter as the
 * ProviderConnectionTester). The result is cheap enough to call on
 * every dashboard load.
 *
 * When a siteaccess is provided, resolves the provider assigned to that
 * siteaccess (findActiveForSiteaccess). Falls back to findActive() (any
 * provider with a chat model) when no siteaccess is given.
 */
readonly class HealthChecker
{
    public function __construct(
        private AiProviderRepositoryInterface $providerRepository,
        private ProviderAdapterRegistry       $adapterRegistry,
        private HttpClientInterface           $httpClient,
    ) {
    }

    public function check(?string $siteaccess = null): HealthReport
    {
        $resolved = $siteaccess !== null
            ? $this->providerRepository->findActiveForSiteaccess($siteaccess)
            : $this->providerRepository->findActive();

        if ($resolved === null) {
            return HealthReport::notConfigured();
        }

        $adapter = $this->adapterRegistry->getForProvider($resolved->providerIdentifier);
        $status = $this->probe($adapter, $resolved);

        return $this->reportFromProbe($adapter, $resolved, $status);
    }

    /**
     * Issue the test request for the resolved provider. Returns one of:
     *   - `int` (HTTP status code) on a successful response
     *   - `string` (error message) on a transport-level failure
     *
     * The string-vs-int union is the seam the mapping helper uses to
     * turn the outcome into a {@see HealthReport}.
     */
    private function probe(ProviderAdapterInterface $adapter, ResolvedProvider $resolved): int|string
    {
        if (!$adapter instanceof TestableProviderAdapterInterface) {
            return 200;
        }

        $url  = $adapter->buildEndpointUrl($resolved->apiUrl);
        $headers = $adapter->buildHeaders($resolved->apiKey);
        $body = $adapter->buildTestRequestBody($resolved->modelIdentifier);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
                'timeout' => 30,
            ]);
            return $response->getStatusCode();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function reportFromProbe(
        ProviderAdapterInterface $adapter,
        ResolvedProvider $resolved,
        int|string $status,
    ): HealthReport {
        if ($status === 200) {
            // A non-testable adapter also lands here, with status=200
            // and a "can't be tested" message attached for the dashboard.
            $message = $adapter instanceof TestableProviderAdapterInterface
                ? null
                : 'Adapter does not support connection testing.';

            return HealthReport::online($resolved->name, $message);
        }

        $message = is_string($status)
            ? $status
            : sprintf('HTTP %d', $status);

        return HealthReport::offline($resolved->name, $message);
    }
}
