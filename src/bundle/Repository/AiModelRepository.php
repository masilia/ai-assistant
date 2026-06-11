<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\Bundle\AiAssistant\Entity\AiModel;

/**
 * Plain Doctrine repository for the AiModel entity. Used by the
 * settings CRUD endpoints (ModelManager, AiProviderApiController::getData)
 * for entity-level operations (find, findAll).
 *
 * @extends ServiceEntityRepository<AiModel>
 */
class AiModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
    }
}
