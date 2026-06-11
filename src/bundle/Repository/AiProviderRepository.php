<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\AiAssistant\Client\Resolved\ResolvedImageTarget;
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
        $provider = $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccessAssignments', 'sa')
            ->innerJoin('p.activeChatModel', 'm')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $provider !== null ? $this->toResolved($provider) : null;
    }

    public function findActive(): ?ResolvedProvider
    {
        $provider = $this->createQueryBuilder('p')
            ->innerJoin('p.activeChatModel', 'm')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $provider !== null ? $this->toResolved($provider) : null;
    }

    public function findActiveImageTarget(string $siteaccess): ?ResolvedImageTarget
    {
        $provider = $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccessAssignments', 'sa')
            ->innerJoin('p.activeImageModel', 'm')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $provider !== null ? $this->toResolvedImageTarget($provider) : null;
    }

    /**
     * Returns the raw AiProvider entity for the active row (any
     * siteaccess scope). Used by the admin dashboard controller.
     */
    public function findActiveEntity(): ?AiProvider
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.activeChatModel', 'm')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Siteaccess-scoped counterpart of {@see findActiveEntity()}.
     */
    public function findActiveEntityForSiteaccess(string $siteaccess): ?AiProvider
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccessAssignments', 'sa')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all providers assigned to a given siteaccess.
     *
     * @return AiProvider[]
     */
    public function findBySiteaccess(string $siteaccess): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccessAssignments', 'sa')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all providers that have at least one siteaccess assignment.
     *
     * @return AiProvider[]
     */
    public function findAllWithSiteaccess(): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccessAssignments', 'sa')
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();
    }

    private function toResolved(AiProvider $provider): ?ResolvedProvider
    {
        $chatModel = $provider->getActiveChatModel();
        if ($chatModel === null) {
            return null;
        }

        return new ResolvedProvider(
            name: $provider->getName(),
            providerIdentifier: $provider->getIdentifier(),
            apiKey: $provider->getApiKey(),
            apiUrl: $provider->getApiUrl(),
            modelIdentifier: $chatModel->getIdentifier(),
            temperature: $chatModel->getTemperature(),
            maxTokens: $chatModel->getMaxTokens(),
        );
    }

    private function toResolvedImageTarget(AiProvider $provider): ?ResolvedImageTarget
    {
        $imageModel = $provider->getActiveImageModel();
        if ($imageModel === null || $provider->getApiKey() === null) {
            return null;
        }

        return new ResolvedImageTarget(
            providerIdentifier: $provider->getIdentifier(),
            apiKey: $provider->getApiKey(),
            apiUrl: $provider->getApiUrl(),
            imageModelIdentifier: $imageModel->getIdentifier(),
        );
    }
}
