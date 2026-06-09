<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Locale;
use Masilia\AiAssistant\DTO\AiError;
use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\AiSuggestResponse;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\AiPromptBuilder;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\FieldContextExtractor;
use Masilia\AiAssistant\FieldFormat;
use Masilia\AiAssistant\FieldFormatResolver;
use Masilia\AiAssistant\LanguageNormalizer;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

readonly class AiSuggestController
{
    /**
     * Generic, client-safe message returned when the AI backend fails. The
     * detailed reason (which may include upstream provider response bodies,
     * API keys hints, quota info, etc.) is only written to the logs.
     */
    private const GENERIC_SERVICE_ERROR = 'The AI service is currently unavailable. Please try again later or contact an administrator.';

    public function __construct(
        private AiClientInterface     $aiClient,
        private FieldFormatResolver   $formatResolver,
        private AiPromptBuilder       $promptBuilder,
        private PermissionResolver    $permissionResolver,
        private FieldContextExtractor $contextExtractor,
        private LanguageNormalizer    $languageNormalizer,
        private LoggerInterface       $aiLogger,
        private ContentService        $contentService,
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
        if (!$this->permissionResolver->hasAccess('content', 'edit')) {
            return new JsonResponse(AiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
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
        try {
            $content = $this->contentService->loadContent($contentId);
        } catch (\Throwable) {
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
        if (!$this->permissionResolver->hasAccess('content', 'edit')) {
            return new JsonResponse(AiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->decodePayload($request);
        } catch (\JsonException) {
            return new JsonResponse(
                AiError::validationError('Invalid JSON payload')->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $aiRequest = AiSuggestRequest::fromArray($payload);

        $validationError = $this->validate($aiRequest);
        if ($validationError !== null) {
            return new JsonResponse($validationError->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $prepared = $this->prepareSuggestion($aiRequest);
            $suggestion = $this->aiClient->suggest($prepared['systemPrompt'], $prepared['userPrompt']);

            $response = new AiSuggestResponse($suggestion, $prepared['format']->value);

            return new JsonResponse($response->toArray());
        } catch (\RuntimeException $e) {
            $this->aiLogger->error('[AI] Suggestion failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        return json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];
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

    /**
     * Builds the system and user prompts for an AI suggestion request, including
     * sibling-field context and optional translation handling.
     *
     * @return array{systemPrompt: string, userPrompt: string, format: FieldFormat}
     */
    private function prepareSuggestion(AiSuggestRequest $aiRequest): array
    {
        $normalizedLanguage = $this->languageNormalizer->normalize($aiRequest->language);

        $enriched = $this->contextExtractor->extractFromContent($aiRequest, $normalizedLanguage);

        $siblingFields = array_map(
            static fn(SiblingField $f) => $f->toArray(),
            $enriched['siblingFields']
        );

        if (empty($siblingFields) && !empty($aiRequest->siblingFields)) {
            $siblingFields = $aiRequest->siblingFields;
        }

        $matrixContext = null;
        if ($aiRequest->fieldType === 'ezmatrix' && $aiRequest->contentId > 0) {
            $matrixContext = $this->extractMatrixContextForRequest($aiRequest, $normalizedLanguage);
        }

        $currentValue = $aiRequest->currentValue;
        $userPromptText = $aiRequest->prompt;

        if ($aiRequest->sourceLanguage !== '') {
            $normalizedSourceLang = $this->languageNormalizer->normalize($aiRequest->sourceLanguage);
            $sourceValue = $this->contextExtractor->getFieldValueInLanguage(
                $aiRequest,
                $normalizedSourceLang,
                $normalizedLanguage,
            );

            if ($sourceValue !== null && $sourceValue['value'] !== '') {
                if ($aiRequest->fieldType === 'ezmatrix') {
                    $userPromptText = sprintf(
                        "Translate each cell of the following matrix from %s to %s. "
                      . "Output ONLY a JSON object with shape {\"rows\": [{\"cells\": {<col_id>: \"<translated_value>\"}}, ...]}. "
                      . "Preserve the original row order. Plain text only in each cell.\n\n%s",
                        $normalizedSourceLang,
                        $normalizedLanguage,
                        $sourceValue['value']
                    );
                } else {
                    $userPromptText = sprintf(
                        "Translate the following %s content to %s. Only output the translated text, "
                        . "nothing else. Preserve the tone and style of the original.\n\n%s",
                        $normalizedSourceLang,
                        $normalizedLanguage,
                        $sourceValue['value']
                    );
                }
                $currentValue = '';
            }
        }

        $format = $this->formatResolver->resolve($aiRequest->fieldType);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt(
            new \Masilia\AiAssistant\SystemPromptContext(
                format: $format,
                fieldName: $aiRequest->fieldName,
                contentType: $enriched['contentType'],
                language: $normalizedLanguage,
                contentTitle: $enriched['contentTitle'],
                siblingFields: $siblingFields,
                fieldType: $aiRequest->fieldType,
                subFieldKey: $aiRequest->subFieldKey,
                metaKeys: $aiRequest->metaKeys,
            ),
            $this->languageNormalizer,
            $matrixContext,
        );

        $userPrompt = $this->promptBuilder->enrichUserPrompt($userPromptText, $currentValue);

        return [
            'systemPrompt' => $systemPrompt,
            'userPrompt' => $userPrompt,
            'format' => $format,
        ];
    }

    /**
     * Loads the content for a matrix field request and pulls the
     * {headers, rowCount} context via FieldContextExtractor. Returns
     * null on any failure (e.g. content not loaded) so the prompt
     * builder can fall back to its default matrix rules.
     *
     * @return array{headers: array<string,string>, rowCount: int}|null
     */
    private function extractMatrixContextForRequest(
        AiSuggestRequest $aiRequest,
        string $normalizedLanguage,
    ): ?array {
        try {
            $content = $this->contentService->loadContent($aiRequest->contentId);
        } catch (\Throwable) {
            return null;
        }

        $contentType = $content->getContentType();

        // Reuse the identifier resolution logic the FieldContextExtractor
        // applies internally by delegating to a tiny helper that resolves
        // the field identifier from the AI request's display label.
        $identifier = $this->resolveCurrentFieldIdentifier($aiRequest, $contentType);
        if ($identifier === '') {
            return null;
        }

        return $this->contextExtractor
            ->extractMatrixContext($content, $contentType, $identifier, $normalizedLanguage);
    }

    /**
     * Resolves the canonical field identifier from the AI request's
     * display label using the same fuzzy match the FieldContextExtractor
     * uses internally.
     */
    private function resolveCurrentFieldIdentifier(
        AiSuggestRequest $aiRequest,
        \Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType $contentType,
    ): string {
        $resolver = new \Masilia\AiAssistant\Field\FieldIdentifierResolver();
        return $resolver->resolve($aiRequest->fieldName, $contentType);
    }

    #[Route('/admin/api/ai/suggest/stream', name: 'app.ai.suggest.stream', methods: ['POST'])]
    public function suggestStream(Request $request): StreamedResponse
    {
        if (!$this->permissionResolver->hasAccess('content', 'edit')) {
            return $this->streamError(AiError::accessDenied(), Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->decodePayload($request);
        } catch (\JsonException) {
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
            $prepared = $this->prepareSuggestion($aiRequest);
        } catch (\RuntimeException $e) {
            $this->aiLogger->error('[AI] Streaming preparation failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $this->streamError(
                AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR),
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

                echo 'data: ' . json_encode(AiError::serviceUnavailable(self::GENERIC_SERVICE_ERROR)->toArray()) . "\n\n";
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
