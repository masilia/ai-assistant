<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ImageProviderAdapterInterface;
use Masilia\AiAssistant\Client\Resolved\ResolvedImageTarget;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;

/**
 * Resolves the active image generation provider + model for the current siteaccess.
 *
 * Resolution priority:
 *   1. DB provider with image_model_identifier set (siteaccess-scoped → global)
 *   2. YAML config fallback
 *
 * Returns a {@see ResolvedImageTarget} ready to be used by {@see ImageGenerationClient}.
 */
class ImageTargetResolver
{
    private const CONFIG_NAMESPACE = 'masilia_ai_assistant';

    public function __construct(
        private readonly AiProviderRepositoryInterface $providerRepository,
        private readonly ConfigResolverInterface      $configResolver,
        private readonly SiteAccessServiceInterface   $siteAccessService,
    ) {
    }

    public function resolve(): ?ResolvedImageTarget
    {
        $siteaccess = $this->getCurrentSiteaccess();

        // 1. Try DB-configured providers (siteaccess-scoped → global)
        $resolved = $this->providerRepository->findActiveImageTarget($siteaccess);
        if ($resolved !== null) {
            return $resolved;
        }

        // 2. Fall back to siteaccess-aware YAML config
        return $this->buildConfigTarget();
    }

    private function buildConfigTarget(): ?ResolvedImageTarget
    {
        $provider  = $this->configResolver->getParameter('provider', self::CONFIG_NAMESPACE);
        $apiKey    = $this->configResolver->getParameter('api_key', self::CONFIG_NAMESPACE);
        $apiUrl    = $this->configResolver->getParameter('api_url', self::CONFIG_NAMESPACE);
        $imageModel = $this->configResolver->getParameter('image_model', self::CONFIG_NAMESPACE);

        if (empty($apiKey) || empty($imageModel)) {
            return null;
        }

        $providerIdentifier = $provider ?: ProviderId::OPENAI;

        return new ResolvedImageTarget(
            providerIdentifier: $providerIdentifier,
            apiKey: (string) $apiKey,
            apiUrl: $apiUrl ? (string) $apiUrl : null,
            imageModelIdentifier: (string) $imageModel,
        );
    }

    private function getCurrentSiteaccess(): string
    {
        return $this->siteAccessService->getCurrent()?->name ?? 'default';
    }
}
