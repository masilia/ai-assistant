<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\AiAssistant\AiDefaults;
use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiModelRepository;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;

/**
 * Manages AI model configuration: create / update / delete / activate.
 *
 * Each provider retains its own active model. Activating a model
 * deactivates sibling models belonging to the same provider (matches
 * runtime resolution in {@see \\Masilia\\AiAssistant\\Client\\TargetResolver}).
 */
readonly class ModelManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AiModelRepository      $modelRepository,
        private AiProviderRepository   $providerRepository,
    ) {
    }

    public function save(array $data): AiModel
    {
        $id = $data['id'] ?? null;
        $model = $id
            ? $this->modelRepository->find($id) ?? throw new \InvalidArgumentException('Model not found.')
            : new AiModel();

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required.');
        }
        if (empty($data['identifier'])) {
            throw new \InvalidArgumentException('Identifier is required.');
        }
        if (empty($data['providerId'])) {
            throw new \InvalidArgumentException('Provider selection is required.');
        }

        $provider = $this->providerRepository->find($data['providerId'])
            ?? throw new \InvalidArgumentException('Selected provider does not exist.');

        $model->setProvider($provider);
        $model->setName($data['name']);
        $model->setIdentifier($data['identifier']);
        $model->setTemperature((float)($data['temperature'] ?? AiDefaults::TEMPERATURE));
        $model->setMaxTokens((int)($data['maxTokens'] ?? AiDefaults::MAX_TOKENS));
        $model->setIsActive((bool)($data['isActive'] ?? false));

        if ($model->isActive()) {
            $this->deactivateOthers($model);
        }

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        return $model;
    }

    public function delete(int $id): void
    {
        $model = $this->modelRepository->find($id)
            ?? throw new \InvalidArgumentException('Model not found.');

        $this->entityManager->remove($model);
        $this->entityManager->flush();
    }

    public function activate(int $id): AiModel
    {
        $model = $this->modelRepository->find($id)
            ?? throw new \InvalidArgumentException('Model not found.');

        $model->setIsActive(true);
        $this->deactivateOthers($model);
        $this->entityManager->flush();

        return $model;
    }

    /**
     * Deactivates other active models belonging to the same provider.
     */
    private function deactivateOthers(AiModel $activeModel): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(AiModel::class, 'm')
            ->set('m.isActive', 'false')
            ->where('m.id != :id')
            ->andWhere('m.provider = :providerId')
            ->setParameter('id', $activeModel->getId())
            ->setParameter('providerId', $activeModel->getProvider()->getId())
            ->getQuery()
            ->execute();
    }
}
