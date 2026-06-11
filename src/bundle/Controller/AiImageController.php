<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\Client\ImageGenerationClient;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

readonly class AiImageController
{
    use JsonRequestDecoder;
    use RequirePermission;

    private const GENERIC_SERVICE_ERROR = 'The AI image generation service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private ImageGenerationClient $imageClient,
        private PermissionResolver    $permissionResolver,
        private LoggerInterface       $aiLogger,
    ) {
    }

    #[Route('/admin/api/ai/generate-image', name: 'app.ai.generate_image', methods: ['POST'])]
    public function generateImage(Request $request): JsonResponse
    {
        if (($denied = $this->requireContentEdit($this->permissionResolver)) !== null) {
            return $denied;
        }

        $payload = $this->decodeJsonRequest($request);
        if ($payload === null) {
            return new JsonResponse(
                ['error' => 'Invalid JSON payload'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $prompt = $payload['prompt'] ?? '';
        $size   = $payload['size'] ?? null;
        $quality = $payload['quality'] ?? null;

        if ($prompt === '') {
            return new JsonResponse(
                ['error' => 'Missing required field: prompt'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $result = $this->imageClient->generate($prompt, $size, $quality);

            return new JsonResponse($result->toArray());
        } catch (\RuntimeException $e) {
            $this->aiLogger->error('[AI] Image generation failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                ['error' => self::GENERIC_SERVICE_ERROR],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
