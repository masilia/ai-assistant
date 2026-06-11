<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\AiAssistant\ApiKey;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;

/**
 * Manages AI provider configuration: create / update / delete / activate.
 *
 * The "only one active" rule is enforced per-siteaccess scope: a global
 * provider only deactivates other global providers; a scoped provider
 * only deactivates other providers for the same siteaccess.
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
        $provider->setSiteaccess(!empty($data['siteaccess']) ? $data['siteaccess'] : null);

        // Only update the API key when a new one is provided (skip the
        // masked value sent by the frontend for existing keys).
        if (isset($data['apiKey']) && $data['apiKey'] !== ApiKey::MASK && $data['apiKey'] !== '') {
            $provider->setApiKey($data['apiKey']);
        }

        $provider->setApiUrl($data['apiUrl'] ?? null);
        $provider->setIsActive((bool)($data['isActive'] ?? false));

        if ($provider->isActive()) {
            $this->deactivateOthers($provider);
        }

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        return $provider;
    }

    public function delete(int $id): void
    {
        $provider = $this->providerRepository->find($id)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $this->entityManager->remove($provider);
        $this->entityManager->flush();
    }

    public function activate(int $id): AiProvider
    {
        $provider = $this->providerRepository->find($id)
            ?? throw new \InvalidArgumentException('Provider not found.');

        $provider->setIsActive(true);
        $this->deactivateOthers($provider);
        $this->entityManager->flush();

        return $provider;
    }

    /**
     * Deactivates other providers within the same siteaccess scope.
     * A global provider (siteaccess = null) only deactivates other global providers.
     * A scoped provider deactivates other providers for the same siteaccess.
     */
    private function deactivateOthers(AiProvider $activeProvider): void
    {
        $siteaccess = $activeProvider->getSiteaccess();

        $this->entityManager->createQueryBuilder()
            ->update(AiProvider::class, 'p')
            ->set('p.isActive', 'false')
            ->where('p.id != :id')
            ->andWhere('p.siteaccess = :sa OR (p.siteaccess IS NULL AND :sa IS NULL)')
            ->setParameter('id', $activeProvider->getId())
            ->setParameter('sa', $siteaccess)
            ->getQuery()
            ->execute();
    }
}
