<?php

namespace Masilia\AiAssistant\Repository;

use Masilia\AiAssistant\Entity\AiModel;
use Masilia\AiAssistant\Entity\AiProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiModel>
 */
class AiModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
    }

    public function add(AiModel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AiModel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveForProvider(AiProvider $provider): ?AiModel
    {
        return $this->findOneBy(['provider' => $provider, 'isActive' => true]);
    }

    public function findActiveGlobal(): ?AiModel
    {
        return $this->findOneBy(['isActive' => true]);
    }
}
