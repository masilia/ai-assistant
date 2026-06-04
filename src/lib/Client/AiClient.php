<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiModelRepositoryInterface;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider-agnostic, siteaccess-aware AI client.
 *
 * Resolution priority:
 *   1. DB provider scoped to the current siteaccess (isActive + siteaccess match)
 *   2. DB provider scoped globally (isActive + siteaccess IS NULL)
 *   3. YAML config via ConfigResolver (siteaccess-aware, with group/default inheritance)
 *   4. Env var fallback (referenced by YAML defaults)
 */
class AiClient implements AiClientInterface
{
    private const CONFIG_NAMESPACE = 'masilia_ai_assistant';
    private const FALLBACK_PROVIDER = 'openai';

    public function __construct(
        private readonly HttpClientInterface           $httpClient,
        private readonly AiProviderRepositoryInterface $providerRepository,
        private readonly AiModelRepositoryInterface    $modelRepository,
        private readonly ProviderAdapterRegistry       $adapterRegistry,
        private readonly ConfigResolverInterface       $configResolver,
        private readonly SiteAccessServiceInterface    $siteAccessService,
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
     * Resolves the target using the siteaccess-aware priority chain:
     *   DB (scoped → global) → YAML (siteaccess-aware) → env fallback
     */
    private function resolveTarget(): AiTarget
    {
        // 1) Try DB-configured providers (siteaccess-scoped → global)
        $currentSiteaccess = $this->getCurrentSiteaccess();
        $activeProvider = $this->providerRepository->findActiveForSiteaccess($currentSiteaccess);

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

        // 2) Fall back to siteaccess-aware YAML config
        return $this->buildConfigTarget();
    }

    /**
     * Builds target from siteaccess-aware YAML config (via ConfigResolver).
     * ConfigResolver automatically resolves: siteaccess → group → default.
     */
    private function buildConfigTarget(): AiTarget
    {
        $provider  = $this->configResolver->getParameter('provider', self::CONFIG_NAMESPACE);
        $apiKey    = $this->configResolver->getParameter('api_key', self::CONFIG_NAMESPACE);
        $apiUrl    = $this->configResolver->getParameter('api_url', self::CONFIG_NAMESPACE);
        $model     = $this->configResolver->getParameter('model', self::CONFIG_NAMESPACE);
        $temp      = $this->configResolver->getParameter('temperature', self::CONFIG_NAMESPACE);
        $maxTokens = $this->configResolver->getParameter('max_tokens', self::CONFIG_NAMESPACE);

        $providerIdentifier = $provider ?: self::FALLBACK_PROVIDER;

        // Ollama typically runs locally without an API key
        if (empty($apiKey) && $providerIdentifier !== 'ollama') {
            throw new \RuntimeException(
                'No active AI provider is configured and no API key is set for the current siteaccess. '
                . 'Configure a provider in the admin dashboard or set masilia_ai_assistant.system.{scope}.api_key in YAML.'
            );
        }

        $adapter = $this->adapterRegistry->getForProvider($providerIdentifier);

        return new AiTarget(
            adapter: $adapter,
            providerIdentifier: $providerIdentifier,
            modelIdentifier: (string) ($model ?: 'gpt-4o-mini'),
            temperature: (float) ($temp ?: 0.7),
            maxTokens: (int) ($maxTokens ?: 4096),
            url: $adapter->buildEndpointUrl($apiUrl),
            headers: $adapter->buildHeaders($apiKey),
        );
    }

    private function getCurrentSiteaccess(): string
    {
        return $this->siteAccessService->getCurrent()?->name ?? 'default';
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
