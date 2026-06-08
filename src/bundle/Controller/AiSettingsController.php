<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Renders the AI Assistant settings dashboard (React SPA).
 * All CRUD endpoints live in {@see AiProviderApiController}
 * and {@see AiModelApiController}.
 */
class AiSettingsController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    #[Route('/', name: 'app.admin.ai_settings.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->checkAccess();

        return $this->render('@ibexadesign/ai_settings/index.html.twig');
    }

    private function checkAccess(): void
    {
        if (!$this->permissionResolver->hasAccess('setup', 'administrate')) {
            throw $this->createAccessDeniedException('You do not have permission to access AI settings.');
        }
    }
}
