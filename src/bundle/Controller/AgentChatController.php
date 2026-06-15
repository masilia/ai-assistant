<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\Agent\AgentOrchestrator;
use Masilia\AiAssistant\Agent\AgentPlan;
use Masilia\AiAssistant\DTO\AiError;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

readonly class AgentChatController
{
    use JsonRequestDecoder;
    use RequirePermission;

    private const GENERIC_SERVICE_ERROR = 'The AI agent service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private AgentOrchestrator  $orchestrator,
        private PermissionResolver $permissionResolver,
        private LoggerInterface    $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/chat', name: 'app.ai.agent.chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $payload = $this->decodeJsonRequest($request);
        if ($payload === null) {
            return new JsonResponse(
                AiError::validationError('Invalid JSON payload')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $message = trim($payload['message'] ?? '');
        if ($message === '') {
            return new JsonResponse(
                AiError::validationError('Missing required field: message')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $response = $this->orchestrator->chat($message);

            return new JsonResponse($response->toArray());
        } catch (\Throwable $e) {
            $this->aiLogger->error('[AI Agent] Chat failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }

    #[Route('/admin/api/ai/agent/execute', name: 'app.ai.agent.execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $payload = $this->decodeJsonRequest($request);
        if ($payload === null) {
            return new JsonResponse(
                AiError::validationError('Invalid JSON payload')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $steps = $payload['steps'] ?? [];
        if (empty($steps)) {
            return new JsonResponse(
                AiError::validationError('Missing required field: steps')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $plan = new AgentPlan(
                steps: $steps,
                description: $payload['description'] ?? '',
                requiresApproval: false,
            );

            $response = $this->orchestrator->executePlan($plan);

            return new JsonResponse($response->toArray());
        } catch (\Throwable $e) {
            $this->aiLogger->error('[AI Agent] Execution failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
