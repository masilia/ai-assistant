<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\AiAssistant\Service\ModelManager;
use Masilia\AiAssistant\DTO\AiError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/ai/settings/api')]
class AiModelApiController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly ModelManager       $modelManager,
    ) {
    }

    #[Route('/model', name: 'app.admin.ai_model.api.save', methods: ['POST'])]
    public function saveModel(Request $request): JsonResponse
    {
        $this->checkAccess();

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];
            $this->modelManager->save($data);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(AiError::validationError($e->getMessage())->toArray(), Response::HTTP_BAD_REQUEST);
        } catch (\JsonException) {
            return new JsonResponse(AiError::validationError('Invalid JSON payload')->toArray(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/model/{id}', name: 'app.admin.ai_model.api.delete', methods: ['DELETE'])]
    public function deleteModel(int $id): JsonResponse
    {
        $this->checkAccess();

        try {
            $this->modelManager->delete($id);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(AiError::validationError($e->getMessage())->toArray(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/model/{id}/activate', name: 'app.admin.ai_model.api.activate', methods: ['POST'])]
    public function activateModel(int $id): JsonResponse
    {
        $this->checkAccess();

        try {
            $this->modelManager->activate($id);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(AiError::validationError($e->getMessage())->toArray(), Response::HTTP_NOT_FOUND);
        }
    }

    private function checkAccess(): void
    {
        if (!$this->permissionResolver->hasAccess('setup', 'administrate')) {
            throw $this->createAccessDeniedException('You do not have permission to access AI settings.');
        }
    }
}
