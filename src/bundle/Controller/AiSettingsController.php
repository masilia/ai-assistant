<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiModelRepository;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/ai/settings')]
class AiSettingsController extends Controller
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissionResolver,
        private readonly AiProviderRepository $providerRepository,
        private readonly AiModelRepository $modelRepository,
        private readonly ProviderAdapterRegistry $adapterRegistry,
    ) {
    }

    private function checkAccess(): void
    {
        if (!$this->permissionResolver->hasAccess('setup', 'administrate')) {
            throw $this->createAccessDeniedException('You do not have permission to access AI settings.');
        }
    }

    #[Route('/', name: 'app.admin.ai_settings.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->checkAccess();

        return $this->render('@ibexadesign/ai_settings/index.html.twig');
    }

    #[Route('/api/data', name: 'app.admin.ai_settings.api.data', methods: ['GET'])]
    public function getData(): JsonResponse
    {
        $this->checkAccess();

        $providers = $this->providerRepository->findAll();
        $models = $this->modelRepository->findAll();

        $providersData = array_map(static function (AiProvider $provider) {
            return [
                'id' => $provider->getId(),
                'name' => $provider->getName(),
                'identifier' => $provider->getIdentifier(),
                'apiKey' => $provider->getApiKey() ? '••••••••' : null, // Mask API key for security
                'apiUrl' => $provider->getApiUrl(),
                'isActive' => $provider->isActive(),
            ];
        }, $providers);

        $modelsData = array_map(static function (AiModel $model) {
            return [
                'id' => $model->getId(),
                'providerId' => $model->getProvider()->getId(),
                'providerName' => $model->getProvider()->getName(),
                'name' => $model->getName(),
                'identifier' => $model->getIdentifier(),
                'temperature' => $model->getTemperature(),
                'maxTokens' => $model->getMaxTokens(),
                'isActive' => $model->isActive(),
            ];
        }, $models);

        $activeProvider = $this->providerRepository->findActive();
        $activeModel = $this->modelRepository->findActiveGlobal();

        return new JsonResponse([
            'providers' => $providersData,
            'models' => $modelsData,
            'activeProviderId' => $activeProvider?->getId(),
            'activeModelId' => $activeModel?->getId(),
        ]);
    }

    #[Route('/api/provider', name: 'app.admin.ai_provider.api.save', methods: ['POST'])]
    public function saveProvider(Request $request): JsonResponse
    {
        $this->checkAccess();

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            
            $id = $data['id'] ?? null;
            if ($id) {
                $provider = $this->providerRepository->find($id);
                if (!$provider) {
                    return new JsonResponse(['error' => 'Provider not found.'], 404);
                }
            } else {
                $provider = new AiProvider();
            }

            if (empty($data['name'])) {
                return new JsonResponse(['error' => 'Name is required.'], 400);
            }
            if (empty($data['identifier'])) {
                return new JsonResponse(['error' => 'Identifier is required.'], 400);
            }

            $provider->setName($data['name']);
            $provider->setIdentifier($data['identifier']);
            
            // Only update API key if a new one is provided or changed
            if (isset($data['apiKey']) && $data['apiKey'] !== '••••••••' && $data['apiKey'] !== '') {
                $provider->setApiKey($data['apiKey']);
            }
            
            $provider->setApiUrl($data['apiUrl'] ?? null);
            $provider->setIsActive((bool)($data['isActive'] ?? false));

            if ($provider->isActive()) {
                $this->deactivateOtherProviders($provider);
            }

            $this->entityManager->persist($provider);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/provider/{id}', name: 'app.admin.ai_provider.api.delete', methods: ['DELETE'])]
    public function deleteProvider(int $id): JsonResponse
    {
        $this->checkAccess();

        $provider = $this->providerRepository->find($id);
        if (!$provider) {
            return new JsonResponse(['error' => 'Provider not found.'], 404);
        }

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/provider/{id}/activate', name: 'app.admin.ai_provider.api.activate', methods: ['POST'])]
    public function activateProvider(int $id): JsonResponse
    {
        $this->checkAccess();

        $provider = $this->providerRepository->find($id);
        if (!$provider) {
            return new JsonResponse(['error' => 'Provider not found.'], 404);
        }

        $provider->setIsActive(true);
        $this->deactivateOtherProviders($provider);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/model', name: 'app.admin.ai_model.api.save', methods: ['POST'])]
    public function saveModel(Request $request): JsonResponse
    {
        $this->checkAccess();

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            
            $id = $data['id'] ?? null;
            if ($id) {
                $model = $this->modelRepository->find($id);
                if (!$model) {
                    return new JsonResponse(['error' => 'Model not found.'], 404);
                }
            } else {
                $model = new AiModel();
            }

            if (empty($data['name'])) {
                return new JsonResponse(['error' => 'Name is required.'], 400);
            }
            if (empty($data['identifier'])) {
                return new JsonResponse(['error' => 'Identifier is required.'], 400);
            }
            if (empty($data['providerId'])) {
                return new JsonResponse(['error' => 'Provider selection is required.'], 400);
            }

            $provider = $this->providerRepository->find($data['providerId']);
            if (!$provider) {
                return new JsonResponse(['error' => 'Selected provider does not exist.'], 400);
            }

            $model->setProvider($provider);
            $model->setName($data['name']);
            $model->setIdentifier($data['identifier']);
            $model->setTemperature((float)($data['temperature'] ?? 0.7));
            $model->setMaxTokens((int)($data['maxTokens'] ?? 2048));
            $model->setIsActive((bool)($data['isActive'] ?? false));

            if ($model->isActive()) {
                $this->deactivateOtherModels($model);
            }

            $this->entityManager->persist($model);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/model/{id}', name: 'app.admin.ai_model.api.delete', methods: ['DELETE'])]
    public function deleteModel(int $id): JsonResponse
    {
        $this->checkAccess();

        $model = $this->modelRepository->find($id);
        if (!$model) {
            return new JsonResponse(['error' => 'Model not found.'], 404);
        }

        $this->entityManager->remove($model);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/model/{id}/activate', name: 'app.admin.ai_model.api.activate', methods: ['POST'])]
    public function activateModel(int $id): JsonResponse
    {
        $this->checkAccess();

        $model = $this->modelRepository->find($id);
        if (!$model) {
            return new JsonResponse(['error' => 'Model not found.'], 404);
        }

        $model->setIsActive(true);
        $this->deactivateOtherModels($model);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/provider/{id}/test', name: 'app.admin.ai_provider.api.test', methods: ['POST'])]
    public function testProvider(int $id, HttpClientInterface $httpClient): JsonResponse
    {
        $this->checkAccess();

        $provider = $this->providerRepository->find($id);
        if (!$provider) {
            return new JsonResponse(['error' => 'Provider not found.'], 404);
        }

        try {
            $adapter   = $this->adapterRegistry->getForProvider($provider->getIdentifier());
            $models    = $provider->getModels();
            $testModel = $models->count() > 0
                ? $models->first()->getIdentifier()
                : $adapter->getDefaultTestModel();

            $url      = $adapter->buildEndpointUrl($provider->getApiUrl());
            $headers  = $adapter->buildHeaders($provider->getApiKey());
            $body     = $adapter->buildTestRequestBody($testModel);
            $response = $httpClient->request('POST', $url, ['headers' => $headers, 'json' => $body]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                return new JsonResponse(['success' => true, 'message' => 'Connection successful!']);
            }

            return new JsonResponse([
                'success' => false,
                'message' => sprintf('API returned HTTP %d: %s', $statusCode, $response->getContent(false)),
            ], 400);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function deactivateOtherProviders(AiProvider $activeProvider): void
    {
        $this->entityManager->createQuery(
            sprintf('UPDATE %s p SET p.isActive = false WHERE p.id != :id', AiProvider::class)
        )->setParameter('id', $activeProvider->getId())->execute();
    }

    private function deactivateOtherModels(AiModel $activeModel): void
    {
        $this->entityManager->createQuery(
            sprintf('UPDATE %s m SET m.isActive = false WHERE m.id != :id', AiModel::class)
        )->setParameter('id', $activeModel->getId())->execute();
    }
}
