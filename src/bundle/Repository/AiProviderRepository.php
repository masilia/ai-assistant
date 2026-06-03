<?php

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

    public function add(AiProvider $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AiProvider $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActive(): ?AiProvider
    {
        return $this->findOneBy(['isActive' => true]);
    }
}
