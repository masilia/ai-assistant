<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\AiAssistant\DTO\AiError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralised permission checks for the AI assistant controllers.
 *
 * The AI assistant dashboard is a React SPA, so every AI endpoint must
 * answer with a JSON 403 (not a Symfony HTML access-denied page) when
 * the editor lacks the right. Each check returns either `null` (allowed)
 * or a `JsonResponse` to send back, so the controller can early-return
 * without an `if`/`else` ladder.
 */
trait RequirePermission
{
    private function requireContentEdit(PermissionResolver $resolver): ?JsonResponse
    {
        if ($resolver->hasAccess('content', 'edit')) {
            return null;
        }

        return new JsonResponse(AiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
    }

    private function requireSetupAdministrate(PermissionResolver $resolver): ?JsonResponse
    {
        if ($resolver->hasAccess('setup', 'administrate')) {
            return null;
        }

        return new JsonResponse(AiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
    }
}
