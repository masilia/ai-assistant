<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\AiAssistant\AiDefaults;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;

/**
 * Resolves the active AI provider + model + endpoint for the current siteaccess.
 *
 * Resolution priority:
 *   1. DB provider scoped to the current siteaccess (isActive + siteaccess match)
 *   2. DB provider scoped globally (isActive + siteaccess IS NULL)
 *   3. YAML config via ConfigResolver (siteaccess-aware, with group/default inheritance)
 *   4. Env var fallback (referenced by YAML defaults)
 *
 * Returns an {@see AiTarget} ready to be used by {@see AiClient}.
 */
class TargetResolver
{
    private const CONFIG_NAMESPACE = 'masilia_ai_assistant';
    private const FALLBACK_PROVIDER = ProviderId::OPENAI;

    public function __construct(
        private readonly AiProviderRepositoryInterface $providerRepository,
        private readonly ProviderAdapterRegistry       $adapterRegistry,
        private readonly ConfigResolverInterface       $configResolver,
        private readonly SiteAccessServiceInterface    $siteAccessService,
    ) {
    }

    public function resolve(): AiTarget
    {
        // 1) Try DB-configured providers (siteaccess-scoped → global)
        $resolved = $this->providerRepository->findActiveForSiteaccess($this->getCurrentSiteaccess());

        if ($resolved !== null) {
            return $this->buildTargetFromResolved($resolved);
        }

        // 2) Fall back to siteaccess-aware YAML config
        return $this->buildConfigTarget();
    }

    private function buildTargetFromResolved(\Masilia\AiAssistant\Client\Resolved\ResolvedProvider $resolved): AiTarget
    {
        $adapter = $this->adapterRegistry->getForProvider($resolved->providerIdentifier);

        return new AiTarget(
            adapter: $adapter,
            providerIdentifier: $resolved->providerIdentifier,
            modelIdentifier: $resolved->modelIdentifier,
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
            url: $adapter->buildEndpointUrl($resolved->apiUrl),
            headers: $adapter->buildHeaders($resolved->apiKey),
            siteaccess: $this->getCurrentSiteaccess(),
        );
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
        if (empty($apiKey) && $providerIdentifier !== ProviderId::OLLAMA) {
            throw new \RuntimeException(
                'No active AI provider is configured and no API key is set for the current siteaccess. '
                . 'Configure a provider in the admin dashboard or set masilia_ai_assistant.system.{scope}.api_key in YAML.'
            );
        }

        $adapter = $this->adapterRegistry->getForProvider($providerIdentifier);

        return new AiTarget(
            adapter: $adapter,
            providerIdentifier: $providerIdentifier,
            modelIdentifier: (string) ($model ?: AiDefaults::MODEL),
            temperature: (float) ($temp ?: AiDefaults::TEMPERATURE),
            maxTokens: (int) ($maxTokens ?: AiDefaults::MAX_TOKENS),
            url: $adapter->buildEndpointUrl($apiUrl),
            headers: $adapter->buildHeaders($apiKey),
            siteaccess: $this->getCurrentSiteaccess(),
        );
    }

    private function getCurrentSiteaccess(): string
    {
        return $this->siteAccessService->getCurrent()?->name ?? 'default';
    }
}
