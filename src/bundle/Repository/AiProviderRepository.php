<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Masilia\AiAssistant\Repository\AiProviderRepositoryInterface;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiProvider>
 */
class AiProviderRepository extends ServiceEntityRepository implements AiProviderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProvider::class);
    }

    public function findActiveForSiteaccess(string $siteaccess): ?AiProvider
    {
        // 1. Try siteaccess-specific provider
        $scoped = $this->findOneBy(['isActive' => true, 'siteaccess' => $siteaccess]);
        if ($scoped !== null) {
            return $scoped;
        }

        // 2. Fall back to global provider (siteaccess = null)
        return $this->findOneBy(['isActive' => true, 'siteaccess' => null]);
    }

    public function findActive(): ?AiProvider
    {
        return $this->findOneBy(['isActive' => true]);
    }
}
