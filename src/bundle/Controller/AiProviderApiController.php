<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\AiAssistant\Entity\AiModel;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Masilia\Bundle\AiAssistant\Repository\AiModelRepository;
use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Masilia\Bundle\AiAssistant\Service\HealthChecker;
use Masilia\Bundle\AiAssistant\Service\ProviderConnectionTester;
use Masilia\Bundle\AiAssistant\Service\ProviderManager;
use Masilia\AiAssistant\DTO\AiError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/ai/settings/api')]
class AiProviderApiController extends Controller
{
    public function __construct(
        private readonly PermissionResolver         $permissionResolver,
        private readonly AiProviderRepository       $providerRepository,
        private readonly AiModelRepository          $modelRepository,
        private readonly ProviderManager            $providerManager,
        private readonly ProviderConnectionTester   $connectionTester,
        private readonly HealthChecker              $healthChecker,
        private readonly SiteAccessServiceInterface $siteAccessService,
    ) {
    }

    #[Route('/data', name: 'app.admin.ai_settings.api.data', methods: ['GET'])]
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
                'siteaccess' => $provider->getSiteaccess(),
                'apiKey' => $provider->getApiKey() ? '••••••••' : null,
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

        $activeProvider = $this->providerRepository->findActiveEntity();
        $activeModel = $activeProvider !== null
            ? $this->modelRepository->findActiveForProvider($activeProvider)
            : null;

        $siteaccesses = [];
        foreach ($this->siteAccessService->getAll() as $sa) {
            $siteaccesses[] = $sa->name;
        }
        sort($siteaccesses);

        $currentSiteaccess = $this->siteAccessService->getCurrent()?->name ?? 'default';

        return new JsonResponse([
            'providers' => $providersData,
            'models' => $modelsData,
            'activeProviderId' => $activeProvider?->getId(),
            'activeModelId' => $activeModel?->getId(),
            'siteaccesses' => $siteaccesses,
            'currentSiteaccess' => $currentSiteaccess,
        ]);
    }

    #[Route('/provider', name: 'app.admin.ai_provider.api.save', methods: ['POST'])]
    public function saveProvider(Request $request): JsonResponse
    {
        $this->checkAccess();

        try {
            $data = $this->decodeJson($request);
            $this->providerManager->save($data);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage());
        } catch (\JsonException) {
            return $this->validationError('Invalid JSON payload');
        }
    }

    #[Route('/provider/{id}', name: 'app.admin.ai_provider.api.delete', methods: ['DELETE'])]
    public function deleteProvider(int $id): JsonResponse
    {
        $this->checkAccess();

        try {
            $this->providerManager->delete($id);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/activate', name: 'app.admin.ai_provider.api.activate', methods: ['POST'])]
    public function activateProvider(int $id): JsonResponse
    {
        $this->checkAccess();

        try {
            $this->providerManager->activate($id);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/test', name: 'app.admin.ai_provider.api.test', methods: ['POST'])]
    public function testProvider(int $id, Request $request): JsonResponse
    {
        $this->checkAccess();

        // ?stream=1 also exercises the SSE path. Useful for catching the
        // "non-streaming works but streaming is misconfigured" failure
        // mode (wrong endpoint suffix, missing stream flag, etc.).
        $testStream = $request->query->getBoolean('stream');

        try {
            $result = $this->connectionTester->test($id, $testStream);

            return new JsonResponse($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new JsonResponse(
                AiError::serviceUnavailable('Connection failed: ' . $e->getMessage())->toArray(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/health', name: 'app.admin.ai_settings.api.health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $this->checkAccess();

        return new JsonResponse($this->healthChecker->check());
    }

    private function checkAccess(): void
    {
        if (!$this->permissionResolver->hasAccess('setup', 'administrate')) {
            throw $this->createAccessDeniedException('You do not have permission to access AI settings.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        return json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    private function validationError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(AiError::validationError($message)->toArray(), $status);
    }
}
