<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Contracts\Core\Repository\Values\User\User;
use Masilia\AiAssistant\Agent\AgentOrchestrator;
use Masilia\AiAssistant\Agent\Wizard\MessageTransformer;
use Masilia\AiAssistant\Agent\Wizard\WizardStoreInterface;
use Masilia\AiAssistant\DTO\AiError;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Throwable;

readonly class AgentChatController
{
    use JsonRequestDecoder;
    use RequirePermission;

    private const GENERIC_SERVICE_ERROR = 'The AI agent service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private AgentOrchestrator     $agentOrchestrator,
        private WizardStoreInterface  $wizardStore,
        private PermissionResolver    $permissionResolver,
        private Security              $security,
        private LoggerInterface       $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/agent/chat', name: 'app.ai.agent.chat', methods: ['POST'])]
    public function chat(Request $request): Response
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
        $selectedOption = $payload['selected_option'] ?? null;

        if ($message === '' && $selectedOption === null) {
            return new JsonResponse(
                AiError::validationError('Missing required field: message or selected_option')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $userId = $this->resolveUserId();
        if ($userId === null) {
            return new JsonResponse(
                AiError::validationError('Authentication required')->toArray(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new StreamedResponse(function () use ($userId, $message, $selectedOption) {
            try {
                $events = $this->agentOrchestrator->runStream($userId, $message, $selectedOption);

                foreach ($events as $event) {
                    echo 'data: ' . json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                echo "data: [DONE]\n\n";
            } catch (Throwable $e) {
                $this->aiLogger->error('[AI Agent] Chat failed: {message}', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);

                $error = ['type' => 'error', 'message' => self::GENERIC_SERVICE_ERROR];
                echo 'data: ' . json_encode($error, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n\n";
            }

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    #[Route('/admin/api/ai/agent/clear', name: 'app.ai.agent.clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $userId = $this->resolveUserId();
        if ($userId === null) {
            return new JsonResponse(
                AiError::validationError('Authentication required')->toArray(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $this->wizardStore->clear($userId);

            return new JsonResponse(['success' => true]);
        } catch (Throwable $e) {
            $this->aiLogger->error('[AI Agent] Clear failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }

    #[Route('/admin/api/ai/agent/history', name: 'app.ai.agent.history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $userId = $this->resolveUserId();
        if ($userId === null) {
            return new JsonResponse(['messages' => []]);
        }

        $state = $this->wizardStore->get($userId);
        if ($state === null) {
            return new JsonResponse(['messages' => []]);
        }

        return new JsonResponse([
            'messages' => MessageTransformer::toFrontend($state->messages),
        ]);
    }

    private function resolveUserId(): ?int
    {
        $user = $this->security->getUser();

        if ($user instanceof \Ibexa\Core\MVC\Symfony\Security\User) {
            return $user->getAPIUser()->id;
        }

        if (method_exists($user, 'getId')) {
            $id = $user->getId();

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }
}
