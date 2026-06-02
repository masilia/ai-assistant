<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Masilia\AiAssistant\DTO\AiSuggestRequest;
use Masilia\AiAssistant\DTO\SiblingField;
use DOMDocument;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class FieldContextExtractor
{
    public function __construct(
        private ContentService   $contentService,
        private FieldTypeService $fieldTypeService,
        private LoggerInterface  $logger,
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
    ): array
    {
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

            $stringValue = $this->fieldToString($field, $fieldDef, $content);
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

    public function fieldToString(Field $field, FieldDefinition $fieldDef, Content $content): string
    {
        $value = $field->value;
        if ($value === null) {
            return '';
        }

        $typeIdentifier = $fieldDef->fieldTypeIdentifier;

        if ($typeIdentifier === 'ezrichtext') {
            if (property_exists($value, 'xml') && $value->xml instanceof DOMDocument) {
                return trim(strip_tags($value->xml->saveHTML()));
            }
            return '';
        }

        if (in_array($typeIdentifier, ['ezimage', 'ezimageasset', 'ezbinaryfile', 'ezmedia'], true)) {
            if (property_exists($value, 'fileName') && $value->fileName) {
                return (string)$value->fileName;
            }
            return '';
        }

        if ($typeIdentifier === 'ezobjectrelation') {
            $relId = $value->destinationContentId ?? null;
            if ($relId) {
                try {
                    return $this->contentService->loadContent((int)$relId)->getName() ?? '';
                } catch (Throwable) {
                    return '';
                }
            }
            return '';
        }

        if ($typeIdentifier === 'ezobjectrelationlist') {
            $ids = $value->destinationContentIds ?? [];
            $names = [];
            foreach (array_slice($ids, 0, 5) as $relId) {
                try {
                    $names[] = $this->contentService->loadContent((int)$relId)->getName() ?? '';
                } catch (Throwable) {
                }
            }
            return implode(', ', array_filter($names));
        }

        if ($typeIdentifier === 'ezselection') {
            $options = $fieldDef->fieldSettings['options'] ?? [];
            $selected = $value->selection ?? [];
            $labels = array_intersect_key($options, array_flip($selected));
            return implode(', ', $labels);
        }

        if ($typeIdentifier === 'ezmatrix') {
            if (method_exists($value, 'getRows')) {
                $lines = [];
                foreach (array_slice(iterator_to_array($value->getRows()), 0, 10) as $row) {
                    $lines[] = implode(' | ', $row->getCells());
                }
                return implode("\n", $lines);
            }
            return '';
        }

        if ($typeIdentifier === 'ezauthor') {
            $authors = $value->authors ?? [];
            return implode(', ', array_map(fn($a) => $a->name ?? '', $authors));
        }

        if ($typeIdentifier === 'ezgmaplocation') {
            $parts = array_filter([
                $value->address ?? null,
                ($value->latitude ?? null) !== null ? "lat:{$value->latitude}" : null,
                ($value->longitude ?? null) !== null ? "lon:{$value->longitude}" : null,
            ]);
            return implode(', ', $parts);
        }

        if ($typeIdentifier === 'eztags') {
            if (property_exists($value, 'tags') && is_iterable($value->tags)) {
                $keywords = [];
                foreach ($value->tags as $tag) {
                    $keywords[] = $tag->getKeyword() ?? '';
                }
                return implode(', ', array_filter($keywords));
            }
            return '';
        }

        if ($typeIdentifier === 'ezcountry') {
            $countries = $value->countries ?? [];
            return implode(', ', array_column($countries, 'Name'));
        }

        if ($typeIdentifier === 'ezkeyword') {
            return implode(', ', $value->values ?? []);
        }

        try {
            $fieldType = $this->fieldTypeService->getFieldType($typeIdentifier);
            $hash = $fieldType->toHash($value);
            return $this->hashToString($hash);
        } catch (Throwable) {
            if (method_exists($value, '__toString')) {
                return trim((string)$value);
            }
            return '';
        }
    }

    private function hashToString(mixed $hash): string
    {
        if (is_string($hash)) {
            return trim($hash);
        }
        if (is_scalar($hash)) {
            return trim((string)$hash);
        }
        if (is_array($hash)) {
            $parts = [];
            foreach ($hash as $key => $val) {
                $str = $this->hashToString($val);
                if ($str !== '') {
                    $parts[] = is_int($key) ? $str : "$key: $str";
                }
            }
            return implode(', ', $parts);
        }
        return '';
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

        $stringValue = $this->fieldToString($field, $fieldDef, $content);

        if ($stringValue === '') {
            return null;
        }

        return [
            'value' => mb_substr($stringValue, 0, AiConstants::MAX_CURRENT_VALUE_CHARS * 2),
            'label' => $fieldDef->getName() ?: $currentFieldIdentifier,
        ];
    }
}
