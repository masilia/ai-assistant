<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\AiPromptBuilder;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\DTO\AiError;
use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\FieldContextExtractor;
use Masilia\AiAssistant\LanguageNormalizer;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class AiImageController
{
    use JsonRequestDecoder;
    use RequirePermission;

    private const GENERIC_SERVICE_ERROR = 'The AI image generation service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private ImageGenerationClient  $imageClient,
        private PermissionResolver     $permissionResolver,
        private FieldContextExtractor  $contextExtractor,
        private LanguageNormalizer     $languageNormalizer,
        private AiPromptBuilder        $promptBuilder,
        private LoggerInterface        $aiLogger,
    )
    {
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
                AiError::validationError('Invalid JSON payload')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $prompt = $payload['prompt'] ?? '';
        $size = $payload['size'] ?? null;
        $quality = $payload['quality'] ?? null;

        if ($prompt === '') {
            return new JsonResponse(
                AiError::validationError('Missing required field: prompt')->toArray(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $enrichedPrompt = $this->enrichPrompt($payload, $prompt);
            $result = $this->imageClient->generate($enrichedPrompt, $size, $quality);

            return new JsonResponse($result->toArray());
        } catch (ClientExceptionInterface|DecodingExceptionInterface
        |RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            $this->aiLogger->error('[AI] Image generation transport error: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        } catch (RuntimeException $e) {
            $this->aiLogger->error('[AI] Image generation failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }

    private function enrichPrompt(array $payload, string $userPrompt): string
    {
        $contentId = (int)($payload['contentId'] ?? 0);
        if ($contentId <= 0) {
            return $userPrompt;
        }

        $aiRequest = AiSuggestRequest::fromArray($payload);
        $normalizedLanguage = $this->languageNormalizer->normalize($aiRequest->language);

        $enriched = $this->contextExtractor->extractFromContent($aiRequest, $normalizedLanguage);

        $siblingFields = array_map(
            static fn(SiblingField $f) => $f->toArray(),
            $enriched['siblingFields'],
        );
        if (empty($siblingFields) && !empty($aiRequest->siblingFields)) {
            $siblingFields = $aiRequest->siblingFields;
        }

        $fieldDescription = $this->contextExtractor->extractFieldDescription($aiRequest, $normalizedLanguage);

        return $this->promptBuilder->enrichImagePrompt(
            $userPrompt,
            $enriched['contentType'],
            $enriched['contentTitle'],
            $fieldDescription,
            $siblingFields,
        );
    }
}
