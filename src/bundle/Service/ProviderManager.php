<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\AiAssistant\ApiKey;
use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;

/**
 * Manages AI provider configuration: create / update / delete /
 * siteaccess assignment / chat+image model selection.
 */
readonly class ProviderManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AiProviderRepository   $providerRepository,
    ) {
    }

    public function save(array $data): AiProvider
    {
        $id = $data['id'] ?? null;
        $provider = $id
            ? $this->providerRepository->find($id) ?? throw new \InvalidArgumentException('Provider not found.')
            : new AiProvider();

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required.');
        }
        if (empty($data['identifier'])) {
            throw new \InvalidArgumentException('Identifier is required.');
        }

        $provider->setName($data['name']);
        $provider->setIdentifier($data['identifier']);

        if (isset($data['apiKey']) && $data['apiKey'] !== ApiKey::MASK && $data['apiKey'] !== '') {
            $provider->setApiKey($data['apiKey']);
        }

        $provider->setApiUrl($data['apiUrl'] ?? null);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        // Handle siteaccess assignments if provided
        if (isset($data['siteaccesses']) && is_array($data['siteaccesses'])) {
            $this->setSiteaccesses($provider->getId(), $data['siteaccesses']);
        }

        return $provider;
    }

    public function delete(int $id): void
    {
        $provider = $this->providerRepository->find($id)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $this->entityManager->remove($provider);
        $this->entityManager->flush();
    }

    /**
     * Replace all siteaccess assignments for a provider.
     *
     * @param list<string> $siteaccesses
     */
    public function setSiteaccesses(int $providerId, array $siteaccesses): AiProvider
    {
        $provider = $this->providerRepository->find($providerId)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $provider->setSiteaccesses($siteaccesses);
        $this->entityManager->flush();

        return $provider;
    }

    public function setChatModel(int $providerId, ?int $modelId): AiProvider
    {
        return $this->setModel($providerId, $modelId, 'setActiveChatModel');
    }

    public function setImageModel(int $providerId, ?int $modelId): AiProvider
    {
        return $this->setModel($providerId, $modelId, 'setActiveImageModel');
    }

    private function setModel(int $providerId, ?int $modelId, string $setter): AiProvider
    {
        $provider = $this->providerRepository->find($providerId)
            ?? throw new \InvalidArgumentException('Provider not found.');

        if ($modelId !== null) {
            $model = $provider->getModels()->filter(
                fn (AiModel $m) => $m->getId() === $modelId
            )->first() ?: null;

            if ($model === null) {
                throw new \InvalidArgumentException('Model does not belong to this provider.');
            }

            $provider->$setter($model);
        } else {
            $provider->$setter(null);
        }

        $this->entityManager->flush();

        return $provider;
    }
}
