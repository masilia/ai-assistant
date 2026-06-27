<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Locale;
use Masilia\AiAssistant\DTO\AiError;
use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\AiSuggestResponse;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\FieldContextExtractor;
use Masilia\AiAssistant\FieldFormatResolver;
use Masilia\AiAssistant\SuggestionService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

readonly class AiSuggestController
{
    use JsonRequestDecoder;
    use RequirePermission;

    /**
     * Generic, client-safe message returned when the AI backend fails. The
     * detailed reason (which may include upstream provider response bodies,
     * API keys hints, quota info, etc.) is only written to the logs.
     */
    private const GENERIC_SERVICE_ERROR = 'The AI service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private AiClientInterface     $aiClient,
        private FieldFormatResolver   $formatResolver,
        private PermissionResolver    $permissionResolver,
        private SuggestionService     $suggestionService,
        private LoggerInterface       $aiLogger,
        private FieldContextExtractor $contextExtractor,
    )
    {
    }

    #[Route('/admin/api/ai/field-types', name: 'app.ai.field_types', methods: ['GET'])]
    public function getFieldTypes(): JsonResponse
    {
        return new JsonResponse([
            'fieldTypes' => $this->formatResolver->getSupportedFieldTypes(),
        ]);
    }

    #[Route(
        '/admin/api/ai/languages/{contentId}',
        name: 'app.ai.languages',
        requirements: ['contentId' => '\d+'],
        methods: ['GET'],
    )]
    public function getLanguages(int $contentId): JsonResponse
    {
        if (($denied = $this->requireContentEdit($this->permissionResolver)) !== null) {
            return $denied;
        }

        // Source: the existing content's language list.
        //
        // We read the languages the current content already has
        // translations in, NOT the siteaccess-config parameter. Two
        // reasons:
        //  1. The siteaccess config lists every language configured
        //     for the siteaccess — including ones with no content
        //     translations yet. For a "Translate from {language}"
        //     dropdown, the user can only translate FROM a language
        //     that has actual content, so the un-translated entries
        //     are noise.
        //  2. Multi-repo installs can declare different per-repo
        //     language lists. The content's own fields are the only
        //     ground truth that works across repos.
        //
        // The contentId is a required routing-pattern parameter
        // (see the #[Route] above). The pattern requires \d+ so
        // the parameter is guaranteed to be a positive integer by
        // the time we get here.
        $content = $this->contextExtractor->loadOrLog($contentId, 'for language list');
        if ($content === null) {
            // Content not found, not accessible, or deleted. The modal
            // can fall back to a free-text input; return [] rather
            // than a 500.
            return new JsonResponse(['languages' => []]);
        }

        // VersionInfo::getLanguageCodes() returns the canonical list
        // of languages this content version has translations in.
        // Pre-deduplicated and pre-sorted by the framework, derived
        // from the actual loaded fields (not from siteaccess config
        // or any external parameter).
        $codes = $content->versionInfo->languageCodes;
        $defaultLocale = Locale::getDefault();
        $languages = array_map(
            static fn(string $code): array => [
                'code' => $code,
                'name' => Locale::getDisplayName($code, $defaultLocale) ?: $code,
            ],
            $codes
        );

        return new JsonResponse(['languages' => $languages]);
    }

    #[Route('/admin/api/ai/suggest', name: 'app.ai.suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        if (($denied = $this->requireContentEdit($this->permissionResolver)) !== null) {
            return $denied;
        }

        $payload = $this->decodeJsonRequest($request);
        if ($payload === null) {
            return $this->jsonErrorResponse('Invalid JSON payload');
        }

        $aiRequest = AiSuggestRequest::fromArray($payload);

        $validationError = $this->validate($aiRequest);
        if ($validationError !== null) {
            return new JsonResponse($validationError->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $prepared = $this->suggestionService->prepare($aiRequest);
            $suggestion = $this->aiClient->suggest($prepared['systemPrompt'], $prepared['userPrompt']);

            $response = new AiSuggestResponse($suggestion, $prepared['format']->value);

            return new JsonResponse($response->toArray());
        } catch (\RuntimeException $e) {
            $this->aiLogger->error('[AI] Suggestion failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable($e->getMessage())->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }

    private function validate(AiSuggestRequest $aiRequest): ?AiError
    {
        if ($aiRequest->fieldType === '' || $aiRequest->prompt === '') {
            return AiError::validationError('Missing required fields: fieldType, prompt');
        }

        if (!$this->formatResolver->supports($aiRequest->fieldType)) {
            return AiError::unsupportedFieldType($aiRequest->fieldType);
        }

        return null;
    }

    #[Route('/admin/api/ai/suggest/stream', name: 'app.ai.suggest.stream', methods: ['POST'])]
    public function suggestStream(Request $request): StreamedResponse
    {
        $denied = $this->requireContentEdit($this->permissionResolver);
        if ($denied !== null) {
            return $this->streamError(AiError::accessDenied(), Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeJsonRequest($request);
        if ($payload === null) {
            return $this->streamError(
                AiError::validationError('Invalid JSON payload'),
                Response::HTTP_BAD_REQUEST
            );
        }

        $aiRequest = AiSuggestRequest::fromArray($payload);

        $validationError = $this->validate($aiRequest);
        if ($validationError !== null) {
            return $this->streamError($validationError, Response::HTTP_BAD_REQUEST);
        }

        try {
            $prepared = $this->suggestionService->prepare($aiRequest);
        } catch (\RuntimeException $e) {
            $this->aiLogger->error('[AI] Streaming preparation failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $this->streamError(
                AiError::serviceUnavailable($e->getMessage()),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $systemPrompt = $prepared['systemPrompt'];
        $userPrompt = $prepared['userPrompt'];
        $formatValue = $prepared['format']->value;

        $response = new StreamedResponse(function () use ($systemPrompt, $userPrompt, $formatValue) {
            try {
                foreach ($this->aiClient->suggestStream($systemPrompt, $userPrompt) as $token) {
                    echo 'data: ' . json_encode(['token' => $token, 'done' => false]) . "\n\n";
                    flush();
                }

                echo 'data: ' . json_encode(['token' => '', 'done' => true, 'format' => $formatValue]) . "\n\n";
                flush();
            } catch (\RuntimeException $e) {
                $this->aiLogger->error('[AI] Streaming suggestion failed: {message}', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);

                echo 'data: ' . json_encode(AiError::serviceUnavailable($e->getMessage())->toArray()) . "\n\n";
                flush();
            }
        }, Response::HTTP_OK, ['Content-Type' => 'text/event-stream']);

        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function streamError(AiError $error, int $status): StreamedResponse
    {
        return new StreamedResponse(static function () use ($error) {
            echo 'data: ' . json_encode($error->toArray(), JSON_THROW_ON_ERROR) . "\n\n";
            flush();
        }, $status, ['Content-Type' => 'text/event-stream']);
    }
}
