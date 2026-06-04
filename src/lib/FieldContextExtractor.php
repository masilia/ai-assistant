<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\Field\FieldValueStringifierRegistry;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Extracts contextual field data from Ibexa content for AI prompts.
 *
 * Field-value-to-string conversion is delegated to the
 * {@see FieldValueStringifierRegistry} (one stringifier class per field type),
 * following the same tagged-iterator pattern as the app's FieldValueTransformer.
 */
readonly class FieldContextExtractor
{
    public function __construct(
        private ContentService                $contentService,
        private FieldValueStringifierRegistry $stringifierRegistry,
        private LoggerInterface               $logger,
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
            return [
                'contentTitle' => $request->contentTitle,
                'contentType' => $request->contentType,
                'siblingFields' => [],
            ];
        }

        try {
            $content = $this->contentService->loadContent($request->contentId);
        } catch (Throwable $e) {
            $this->logger->warning(
                '[AI] Failed to load content {contentId}: {message}',
                ['contentId' => $request->contentId, 'message' => $e->getMessage()]
            );

            return [
                'contentTitle' => $request->contentTitle,
                'contentType' => $request->contentType,
                'siblingFields' => [],
            ];
        }

        $contentTypeObj = $content->getContentType();
        $contentTitle = $request->contentTitle;
        $contentType = $contentTypeObj->getName();

        $resolvedTitle = $content->getName($normalizedLanguage) ?? $content->getName();
        if ($resolvedTitle !== null && $resolvedTitle !== '') {
            $contentTitle = $resolvedTitle;
        }

        $currentFieldIdentifier = $this->resolveCurrentFieldIdentifier(
            $request->fieldName, $contentTypeObj
        );

        $siblingFields = $this->extractSiblingFields(
            $content, $contentTypeObj, $currentFieldIdentifier, $normalizedLanguage
        );

        return [
            'contentTitle' => $contentTitle,
            'contentType' => $contentType,
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
        $currentFieldIdentifier = $this->resolveCurrentFieldIdentifier($request->fieldName, $contentType);

        if ($currentFieldIdentifier === '') {
            return null;
        }

        $fieldDef = $contentType->getFieldDefinition($currentFieldIdentifier);
        if ($fieldDef === null) {
            return null;
        }

        $field = $content->getField($currentFieldIdentifier, $sourceLanguage)
            ?? $content->getField($currentFieldIdentifier);

        if ($field === null) {
            return null;
        }

        $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);

        if ($stringValue === '') {
            return null;
        }

        return [
            'value' => mb_substr($stringValue, 0, AiConstants::MAX_CURRENT_VALUE_CHARS * 2),
            'label' => $fieldDef->getName() ?: $currentFieldIdentifier,
        ];
    }

    // -----------------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------------

    private function resolveCurrentFieldIdentifier(string $fieldName, ContentType $contentType): string
    {
        if ($fieldName === '') {
            return '';
        }

        $normalised = mb_strtolower(trim($fieldName));

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            $defName = mb_strtolower(trim($fieldDef->getName() ?? ''));
            if ($defName === $normalised) {
                return $fieldDef->identifier;
            }
        }

        $asIdentifier = strtolower(str_replace(' ', '_', $fieldName));
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if ($fieldDef->identifier === $asIdentifier) {
                return $fieldDef->identifier;
            }
        }

        return $asIdentifier;
    }

    /**
     * @return SiblingField[]
     */
    private function extractSiblingFields(
        Content     $content,
        ContentType $contentType,
        string      $currentFieldIdentifier,
        string      $language,
    ): array {
        $siblingFields = [];

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            $identifier = $fieldDef->identifier;

            if ($identifier === $currentFieldIdentifier) {
                continue;
            }

            $field = $content->getField($identifier, $language)
                ?? $content->getField($identifier);

            if ($field === null) {
                continue;
            }

            $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);
            if ($stringValue === '') {
                continue;
            }

            $label = $fieldDef->getName() ?: $identifier;

            $siblingFields[] = new SiblingField(
                label: $label,
                value: mb_substr($stringValue, 0, AiConstants::MAX_SIBLING_CHARS),
            );
        }

        return $siblingFields;
    }
}
