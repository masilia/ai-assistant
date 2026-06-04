<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\AiAssistant\Repository\AiModelRepositoryInterface;
use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;

/**
 * @extends ServiceEntityRepository<AiModel>
 */
class AiModelRepository extends ServiceEntityRepository implements AiModelRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
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
