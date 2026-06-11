<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\AiAssistant\Service\ModelManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/ai/settings/api')]
class AiModelApiController extends Controller
{
    use JsonRequestDecoder;
    use RequirePermission;

    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly ModelManager       $modelManager,
    ) {
    }

    #[Route('/model', name: 'app.admin.ai_model.api.save', methods: ['POST'])]
    public function saveModel(Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->jsonErrorResponse('Invalid JSON payload');
        }

        try {
            $this->modelManager->save($data);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage());
        }
    }

    #[Route('/model/{id}', name: 'app.admin.ai_model.api.delete', methods: ['DELETE'])]
    public function deleteModel(int $id): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        try {
            $this->modelManager->delete($id);

            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }
}
