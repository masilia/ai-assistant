<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Ibexa\FieldTypeMatrix\FieldType\Value;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldStringValue;
use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\Field\ContentFieldAccessor;
use Masilia\AiAssistant\Field\FieldIdentifierResolver;
use Masilia\AiAssistant\Field\FieldValueStringifierRegistry;
use Masilia\AiAssistant\Field\SiblingFieldsExtractor;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
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
        private ContentService                $contentService,
        private FieldValueStringifierRegistry $stringifierRegistry,
        private FieldIdentifierResolver       $identifierResolver,
        private SiblingFieldsExtractor        $siblingExtractor,
        private LoggerInterface               $aiLogger,
    )
    {
    }

    /**
     * @return array{contentTitle: string, contentType: string, siblingFields: SiblingField[]}
     */
    public function extractFromContent(
        AiSuggestRequest $request,
        string           $normalizedLanguage,
    ): array
    {
        if ($request->contentId <= 0) {
            return $this->emptyContext($request);
        }

        $content = $this->loadOrLog($request->contentId, 'for context extraction');
        if ($content === null) {
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

    /**
     * Load content by id, or null on any failure (not found, not
     * accessible, deleted, etc.) with a warning logged.
     *
     * Centralises what used to be a 4×-inlined try/catch around
     * {@see ContentService::loadContent()} — the only point in the
     * package that knows how to talk to the Ibexa content service.
     */
    public function loadOrLog(int $contentId, string $reason): ?Content
    {
        try {
            return $this->contentService->loadContent($contentId);
        } catch (Throwable $e) {
            $this->aiLogger->warning(
                '[AI] Failed to load content {contentId} {reason}: {message}',
                ['contentId' => $contentId, 'reason' => $reason, 'message' => $e->getMessage()]
            );

            return null;
        }
    }

    private function resolveTitle(Content $content, string $language): ?string
    {
        $name = $content->getName($language) ?? $content->getName();
        return ($name !== null && $name !== '') ? $name : null;
    }

    public function getFieldValueInLanguage(
        AiSuggestRequest $request,
        string           $sourceLanguage,
        string           $targetLanguage,
    ): ?FieldStringValue
    {
        if ($request->contentId <= 0 || $sourceLanguage === '') {
            return null;
        }

        if ($sourceLanguage === $targetLanguage) {
            return null;
        }

        $content = $this->loadOrLog($request->contentId, 'for translation');
        if ($content === null) {
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

        $field = ContentFieldAccessor::getWithFallback($content, $currentIdentifier, $sourceLanguage);

        if ($field === null) {
            return null;
        }

        $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);

        if ($stringValue === '') {
            return null;
        }

        return new FieldStringValue(
            value: mb_substr($stringValue, 0, AiConstants::MAX_CURRENT_VALUE_CHARS * 2),
            label: $fieldDef->getName() ?: $currentIdentifier,
        );
    }

    /**
     * One-shot matrix-context extraction for a request payload: loads the
     * content, resolves the field identifier from the AI request's display
     * label (reusing the same fuzzy match used by the sibling extractor),
     * and returns the {headers, rowCount} context.
     *
     * Returns null on any failure (content not loaded, identifier not
     * resolvable, non-matrix field) so the caller can fall back to
     * default matrix rules.
     *
     * @return array{headers: array<string,string>, rowCount: int}|null
     */
    public function extractMatrixContextForRequest(
        AiSuggestRequest $request,
        string           $normalizedLanguage,
    ): ?array
    {
        $content = $this->loadOrLog($request->contentId, 'for matrix context');
        if ($content === null) {
            return null;
        }

        $contentType = $content->getContentType();
        $identifier = $this->identifierResolver->resolve($request->fieldName, $contentType);
        if ($identifier === '') {
            return null;
        }

        return $this->extractMatrixContext($content, $contentType, $identifier, $normalizedLanguage);
    }

    /**
     * Pulls matrix-specific context (column headers + current row count) for
     * the AI system prompt. Returns null for non-matrix field types so
     * the controller can branch cleanly.
     *
     * @return array{headers: array<string,string>, rowCount: int}|null
     */
    public function extractMatrixContext(
        Content     $content,
        ContentType $contentType,
        string      $fieldIdentifier,
        string      $language,
    ): ?array
    {
        $fieldDef = $contentType->getFieldDefinition($fieldIdentifier);
        if ($fieldDef === null || $fieldDef->fieldTypeIdentifier !== FieldType::EZMATRIX) {
            return null;
        }

        $columns = $fieldDef->getFieldSettings()['columns'] ?? [];
        $headers = [];
        foreach ($columns as $col) {
            if (!isset($col['identifier'])) {
                continue;
            }
            $headers[(string)$col['identifier']] = (string)($col['name'] ?? $col['identifier']);
        }

        $field = ContentFieldAccessor::getWithFallback($content, $fieldIdentifier, $language);

        $rowCount = 0;
        if ($field !== null && $field->value instanceof Value) {
            $rowCount = $field->value->getRows()->count();
        }

        return [
            'headers' => $headers,
            'rowCount' => $rowCount,
        ];
    }
}
