<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\Bundle\AiAssistant\Entity\AiModel;

/**
 * Plain Doctrine repository for the AiModel entity. Used by the
 * settings CRUD endpoints (ModelManager, AiProviderApiController::getData)
 * for entity-level operations (find, findAll, findActive).
 *
 * The lib layer's AiProviderRepositoryInterface no longer exposes
 * "find active model" — the bundle-layer AiProviderRepository merges
 * that into a single ResolvedProvider to keep the lib free of entities.
 *
 * @extends ServiceEntityRepository<AiModel>
 */
class AiModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
    }

    public function findActiveForProvider(\Masilia\Bundle\AiAssistant\Entity\AiProvider $provider): ?AiModel
    {
        return $this->findOneBy(['provider' => $provider, 'isActive' => true]);
    }
}
