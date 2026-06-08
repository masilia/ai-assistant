<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\AiAssistant\Client\Resolved\ResolvedProvider;
use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;

/**
 * @extends ServiceEntityRepository<AiProvider>
 */
class AiProviderRepository extends ServiceEntityRepository implements AiProviderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProvider::class);
    }

    public function findActiveForSiteaccess(string $siteaccess): ?ResolvedProvider
    {
        // 1. Try siteaccess-specific provider
        $provider = $this->findOneBy(['isActive' => true, 'siteaccess' => $siteaccess]);
        if ($provider !== null) {
            return $this->toResolved($provider);
        }

        // 2. Fall back to global provider (siteaccess = null)
        $global = $this->findOneBy(['isActive' => true, 'siteaccess' => null]);
        return $global !== null ? $this->toResolved($global) : null;
    }

    public function findActive(): ?ResolvedProvider
    {
        $provider = $this->findOneBy(['isActive' => true]);
        return $provider !== null ? $this->toResolved($provider) : null;
    }

    /**
     * Returns the raw AiProvider entity for the active row (any
     * siteaccess scope). Used by the admin dashboard controller
     * which needs the DB primary keys of the provider and its
     * active model to highlight them in the response payload.
     *
     * Do not use this from runtime code (TargetResolver). The lib
     * interface is the only public contract for that path.
     */
    public function findActiveEntity(): ?AiProvider
    {
        return $this->findOneBy(['isActive' => true]);
    }

    private function toResolved(AiProvider $provider): ?ResolvedProvider
    {
        $activeModel = $provider->getModels()->filter(
            static fn($m) => $m->isActive()
        )->first() ?: null;

        if ($activeModel === null) {
            return null;
        }

        return new ResolvedProvider(
            name: $provider->getName(),
            providerIdentifier: $provider->getIdentifier(),
            apiKey: $provider->getApiKey(),
            apiUrl: $provider->getApiUrl(),
            modelIdentifier: $activeModel->getIdentifier(),
            temperature: $activeModel->getTemperature(),
            maxTokens: $activeModel->getMaxTokens(),
        );
    }
}
