<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Masilia\AiAssistant\DTO\AiError;
use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\AiSuggestResponse;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\AiPromptBuilder;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\FieldContextExtractor;
use Masilia\AiAssistant\FieldFormatResolver;
use Masilia\AiAssistant\LanguageNormalizer;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

readonly class AiSuggestController
{
    public function __construct(
        private AiClientInterface     $aiClient,
        private FieldFormatResolver   $formatResolver,
        private AiPromptBuilder       $promptBuilder,
        private PermissionResolver    $permissionResolver,
        private FieldContextExtractor $contextExtractor,
        private LanguageNormalizer    $languageNormalizer,
        private LoggerInterface       $logger,
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

    #[Route('/admin/api/ai/suggest', name: 'app.ai.suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new JsonResponse(
                AiError::accessDenied()->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];

        $aiRequest = AiSuggestRequest::fromArray($payload);
        $normalizedLanguage = $this->languageNormalizer->normalize($aiRequest->language);

        if ($aiRequest->fieldType === '' || $aiRequest->prompt === '') {
            return new JsonResponse(
                AiError::validationError('Missing required fields: fieldType, prompt')->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->formatResolver->supports($aiRequest->fieldType)) {
            return new JsonResponse(
                AiError::unsupportedFieldType($aiRequest->fieldType)->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $enriched = $this->contextExtractor->extractFromContent($aiRequest, $normalizedLanguage);

        $siblingFields = array_map(
            static fn(SiblingField $f) => $f->toArray(),
            $enriched['siblingFields']
        );

        if (empty($siblingFields) && !empty($aiRequest->siblingFields)) {
            $siblingFields = $aiRequest->siblingFields;
        }

        $currentValue = $aiRequest->currentValue;
        $userPromptText = $aiRequest->prompt;

        if ($aiRequest->sourceLanguage !== '') {
            $sourceValue = $this->contextExtractor->getFieldValueInLanguage(
                $aiRequest,
                $this->languageNormalizer->normalize($aiRequest->sourceLanguage),
                $normalizedLanguage,
            );

            if ($sourceValue !== null && $sourceValue['value'] !== '') {
                $currentValue = $sourceValue['value'];
                $sourceLabel = $sourceValue['label'];
                $normalizedSourceLang = $this->languageNormalizer->normalize($aiRequest->sourceLanguage);
                $userPromptText = sprintf(
                    'Translate the following %s content to %s. Only output the translated text, nothing else. Preserve the tone and style of the original.\n\n%s',
                    $normalizedSourceLang,
                    $normalizedLanguage,
                    $currentValue
                );
                $currentValue = '';
            }
        }

        try {
            $format = $this->formatResolver->resolve($aiRequest->fieldType);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt(
                $format,
                $aiRequest->fieldName,
                $enriched['contentType'],
                $normalizedLanguage,
                $enriched['contentTitle'],
                $siblingFields,
                $this->languageNormalizer,
            );
            $userPrompt = $this->promptBuilder->enrichUserPrompt(
                $userPromptText,
                $currentValue
            );
            $suggestion = $this->aiClient->suggest($systemPrompt, $userPrompt);

            $response = new AiSuggestResponse($suggestion, $format->value);

            return new JsonResponse($response->toArray());
        } catch (\RuntimeException $e) {
            $this->logger->error('[AI] Suggestion failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return new JsonResponse(
                AiError::serviceUnavailable($e->getMessage())->toArray(),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }

    #[Route('/admin/api/ai/suggest/stream', name: 'app.ai.suggest.stream', methods: ['POST'])]
    public function suggestStream(Request $request): StreamedResponse
    {
        if ($this->permissionResolver->hasAccess('content', 'edit') === false) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(AiError::accessDenied()->toArray()) . "\n\n";
            }, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/event-stream']);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $e) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(AiError::validationError('Invalid JSON payload')->toArray()) . "\n\n";
            }, Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/event-stream']);
        }

        $aiRequest = AiSuggestRequest::fromArray($payload);
        $normalizedLanguage = $this->languageNormalizer->normalize($aiRequest->language);

        if ($aiRequest->fieldType === '' || $aiRequest->prompt === '') {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(AiError::validationError('Missing required fields: fieldType, prompt')->toArray()) . "\n\n";
            }, Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/event-stream']);
        }

        if (!$this->formatResolver->supports($aiRequest->fieldType)) {
            return new StreamedResponse(function () use ($aiRequest) {
                echo "data: " . json_encode(AiError::unsupportedFieldType($aiRequest->fieldType)->toArray()) . "\n\n";
            }, Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/event-stream']);
        }

        $enriched = $this->contextExtractor->extractFromContent($aiRequest, $normalizedLanguage);

        $siblingFields = array_map(
            static fn(SiblingField $f) => $f->toArray(),
            $enriched['siblingFields']
        );

        if (empty($siblingFields) && !empty($aiRequest->siblingFields)) {
            $siblingFields = $aiRequest->siblingFields;
        }

        $currentValue = $aiRequest->currentValue;
        $userPromptText = $aiRequest->prompt;

        if ($aiRequest->sourceLanguage !== '') {
            $sourceValue = $this->contextExtractor->getFieldValueInLanguage(
                $aiRequest,
                $this->languageNormalizer->normalize($aiRequest->sourceLanguage),
                $normalizedLanguage,
            );

            if ($sourceValue !== null && $sourceValue['value'] !== '') {
                $currentValue = $sourceValue['value'];
                $normalizedSourceLang = $this->languageNormalizer->normalize($aiRequest->sourceLanguage);
                $userPromptText = sprintf(
                    'Translate the following %s content to %s. Only output the translated text, nothing else. Preserve the tone and style of the original.\n\n%s',
                    $normalizedSourceLang,
                    $normalizedLanguage,
                    $currentValue
                );
                $currentValue = '';
            }
        }

        try {
            $format = $this->formatResolver->resolve($aiRequest->fieldType);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt(
                $format,
                $aiRequest->fieldName,
                $enriched['contentType'],
                $normalizedLanguage,
                $enriched['contentTitle'],
                $siblingFields,
                $this->languageNormalizer,
            );
            $userPrompt = $this->promptBuilder->enrichUserPrompt(
                $userPromptText,
                $currentValue
            );

            $formatValue = $format->value;

            $response = new StreamedResponse(function () use ($systemPrompt, $userPrompt, $formatValue) {
                $tokens = $this->aiClient->suggestStream($systemPrompt, $userPrompt);

                foreach ($tokens as $token) {
                    $chunk = json_encode(['token' => $token, 'done' => false]);
                    echo "data: {$chunk}\n\n";
                    flush();
                }

                $done = json_encode(['token' => '', 'done' => true, 'format' => $formatValue]);
                echo "data: {$done}\n\n";
                flush();
            }, Response::HTTP_OK, ['Content-Type' => 'text/event-stream']);

            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('X-Accel-Buffering', 'no');

            return $response;
        } catch (\RuntimeException $e) {
            $this->logger->error('[AI] Streaming suggestion failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return new StreamedResponse(function () use ($e) {
                echo "data: " . json_encode(AiError::serviceUnavailable($e->getMessage())->toArray()) . "\n\n";
            }, Response::HTTP_SERVICE_UNAVAILABLE, ['Content-Type' => 'text/event-stream']);
        }
    }
}
