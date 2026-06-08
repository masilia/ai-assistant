<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\Field\FieldIdentifierResolver;
use Masilia\AiAssistant\Field\FieldValueStringifierRegistry;
use Masilia\AiAssistant\Field\SiblingFieldsExtractor;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Extracts contextual field data from Ibexa content for AI prompts.
 *
 * Orchestrator: loads the content via {@see ContentService}, then delegates
 * identifier resolution and sibling extraction to dedicated helpers.
 *
 * Field-value-to-string conversion is delegated to the
 * {@see FieldValueStringifierRegistry}.
 */
readonly class FieldContextExtractor
{
    public function __construct(
        private ContentService           $contentService,
        private FieldValueStringifierRegistry $stringifierRegistry,
        private FieldIdentifierResolver  $identifierResolver,
        private SiblingFieldsExtractor   $siblingExtractor,
        private LoggerInterface          $logger,
    ) {
    }

    /**
     * @return array{contentTitle: string, contentType: string, siblingFields: SiblingField[]}
     */
    public function extractFromContent(
        AiSuggestRequest $request,
        string           $normalizedLanguage,
    ): array {
        if ($request->contentId <= 0) {
            return $this->emptyContext($request);
        }

        try {
            $content = $this->contentService->loadContent($request->contentId);
        } catch (Throwable $e) {
            $this->logger->warning(
                '[AI] Failed to load content {contentId}: {message}',
                ['contentId' => $request->contentId, 'message' => $e->getMessage()]
            );

            return $this->emptyContext($request);
        }

        $contentType = $content->getContentType();
        $contentTitle = $this->resolveTitle($content, $normalizedLanguage) ?: $request->contentTitle;

        $currentIdentifier = $this->identifierResolver->resolve(
            $request->fieldName, $contentType
        );

        $siblingFields = $this->siblingExtractor->extract(
            $content, $contentType, $currentIdentifier, $normalizedLanguage
        );

        return [
            'contentTitle' => $contentTitle,
            'contentType' => $contentType->getName(),
            'siblingFields' => $siblingFields,
        ];
    }

    /**
     * @return array{value: string, label: string}|null
     */
    public function getFieldValueInLanguage(
        AiSuggestRequest $request,
        string $sourceLanguage,
        string $targetLanguage,
    ): ?array {
        if ($request->contentId <= 0 || $sourceLanguage === '') {
            return null;
        }

        if ($sourceLanguage === $targetLanguage) {
            return null;
        }

        try {
            $content = $this->contentService->loadContent($request->contentId);
        } catch (Throwable $e) {
            $this->logger->warning(
                '[AI] Failed to load content {contentId} for translation: {message}',
                ['contentId' => $request->contentId, 'message' => $e->getMessage()]
            );

            return null;
        }

        $contentType = $content->getContentType();
        $currentIdentifier = $this->identifierResolver->resolve($request->fieldName, $contentType);

        if ($currentIdentifier === '') {
            return null;
        }

        $fieldDef = $contentType->getFieldDefinition($currentIdentifier);
        if ($fieldDef === null) {
            return null;
        }

        $field = $content->getField($currentIdentifier, $sourceLanguage)
            ?? $content->getField($currentIdentifier);

        if ($field === null) {
            return null;
        }

        $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);

        if ($stringValue === '') {
            return null;
        }

        return [
            'value' => mb_substr($stringValue, 0, AiConstants::MAX_CURRENT_VALUE_CHARS * 2),
            'label' => $fieldDef->getName() ?: $currentIdentifier,
        ];
    }

    /**
     * @return array{contentTitle: string, contentType: string, siblingFields: SiblingField[]}
     */
    private function emptyContext(AiSuggestRequest $request): array
    {
        return [
            'contentTitle' => $request->contentTitle,
            'contentType' => $request->contentType,
            'siblingFields' => [],
        ];
    }

    private function resolveTitle(Content $content, string $language): ?string
    {
        $name = $content->getName($language) ?? $content->getName();
        return ($name !== null && $name !== '') ? $name : null;
    }
}
