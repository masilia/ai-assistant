<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\AiAssistant\Entity\AiProvider;
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

        $providersData = array_map(static fn(AiProvider $provider) => $provider->toArray(), $providers);

        $modelsData = [];
        foreach ($providers as $provider) {
            foreach ($provider->getModels() as $model) {
                $modelArray = $model->toArray();
                $modelArray['providerName'] = $provider->getName();
                $modelsData[] = $modelArray;
            }
        }

        $siteaccesses = [];
        foreach ($this->siteAccessService->getAll() as $sa) {
            $siteaccesses[] = $sa->name;
        }
        sort($siteaccesses);

        $currentSiteaccess = $this->siteAccessService->getCurrent()?->name ?? 'default';

        return new JsonResponse([
            'providers' => $providersData,
            'models' => $modelsData,
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
            return $this->jsonErrorResponse('Invalid JSON payload');
        }

        try {
            $this->providerManager->save($data);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage());
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
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/siteaccesses', name: 'app.admin.ai_provider.api.siteaccesses', methods: ['PUT'])]
    public function setSiteaccesses(int $id, Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $data = $this->decodeJsonRequest($request);
        if ($data === null || !isset($data['siteaccesses']) || !is_array($data['siteaccesses'])) {
            return $this->jsonErrorResponse('siteaccesses array is required');
        }

        try {
            $this->providerManager->setSiteaccesses($id, $data['siteaccesses']);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/chat-model', name: 'app.admin.ai_provider.api.chat_model', methods: ['PUT'])]
    public function setChatModel(int $id, Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->jsonErrorResponse('Invalid JSON payload');
        }

        $modelId = $data['modelId'] ?? null;

        try {
            $this->providerManager->setChatModel($id, $modelId !== null ? (int) $modelId : null);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/image-model', name: 'app.admin.ai_provider.api.image_model', methods: ['PUT'])]
    public function setImageModel(int $id, Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->jsonErrorResponse('Invalid JSON payload');
        }

        $modelId = $data['modelId'] ?? null;

        try {
            $this->providerManager->setImageModel($id, $modelId !== null ? (int) $modelId : null);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/provider/{id}/test', name: 'app.admin.ai_provider.api.test', methods: ['POST'])]
    public function testProvider(int $id, Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $testStream = $request->query->getBoolean('stream');

        try {
            $result = $this->connectionTester->test($id, $testStream);

            return new JsonResponse($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
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

        $siteaccess = $this->siteAccessService->getCurrent()?->name;

        return new JsonResponse($this->healthChecker->check($siteaccess)->toArray());
    }


}
