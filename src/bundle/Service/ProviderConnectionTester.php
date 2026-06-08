<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests reachability of an AI provider by issuing a minimal request
 * through its adapter. Returns a structured result for the API response.
 */
class ProviderConnectionTester
{
    public function __construct(
        private readonly AiProviderRepository  $providerRepository,
        private readonly ProviderAdapterRegistry $adapterRegistry,
        private readonly HttpClientInterface    $httpClient,
    ) {
    }

    /**
     * @return array{success: bool, message: string, httpStatus: int|null}
     */
    public function test(int $providerId): array
    {
        $provider = $this->providerRepository->find($providerId)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $adapter = $this->adapterRegistry->getForProvider($provider->getIdentifier());

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
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'httpStatus' => $statusCode,
            ];
        }

        return [
            'success' => false,
            'message' => sprintf('API returned HTTP %d: %s', $statusCode, $response->getContent(false)),
            'httpStatus' => $statusCode,
        ];
    }
}
