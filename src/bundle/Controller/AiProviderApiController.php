<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\AiAssistant\ApiKey;
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
    use JsonRequestDecoder;
    use RequirePermission;

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
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $providers = $this->providerRepository->findAll();
        $models = $this->modelRepository->findAll();

        $providersData = array_map(static function (AiProvider $provider) {
            return [
                'id' => $provider->getId(),
                'name' => $provider->getName(),
                'identifier' => $provider->getIdentifier(),
                'siteaccess' => $provider->getSiteaccess(),
                'apiKey' => $provider->getApiKey() ? ApiKey::MASK : null,
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

        $siteaccesses = [];
        foreach ($this->siteAccessService->getAll() as $sa) {
            $siteaccesses[] = $sa->name;
        }
        sort($siteaccesses);

        $currentSiteaccess = $this->siteAccessService->getCurrent()?->name ?? 'default';

        // Match the runtime resolution path: scoped → global, scoped
        // to the current siteaccess. The previous findActiveEntity()
        // returned the first active row across ALL siteaccess scopes,
        // which made the dashboard's "active provider" highlight flip
        // non-deterministically when two siteaccesses had different
        // active providers.
        $activeProvider = $this->providerRepository->findActiveEntityForSiteaccess($currentSiteaccess);
        $activeModel = $activeProvider !== null
            ? $this->modelRepository->findActiveForProvider($activeProvider)
            : null;

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
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->validationError('Invalid JSON payload');
        }

        try {
            $this->providerManager->save($data);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage());
        }
    }

    #[Route('/provider/{id}', name: 'app.admin.ai_provider.api.delete', methods: ['DELETE'])]
    public function deleteProvider(int $id): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

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
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

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
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

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
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        return new JsonResponse($this->healthChecker->check()->toArray());
    }

    private function validationError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(AiError::validationError($message)->toArray(), $status);
    }
}
