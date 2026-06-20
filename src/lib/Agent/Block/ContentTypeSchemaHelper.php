<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;

/**
 * Shared logic for describing content type field schemas.
 *
 * Used by both BlockCatalog (block content types) and
 * ContentCatalog (standard content types like page, article, etc.).
 */
trait ContentTypeSchemaHelper
{
    /**
     * Build a detailed schema entry for a single field definition.
     *
     * Always returns at minimum `{type, required, translatable}`. Adds `description`
     * when the field definition has a non-empty admin description, plus field-type-
     * specific extras (columns, allowedTypes, options, length/size limits, etc.).
     *
     * @return array<string, mixed>
     */
    private function describeField(FieldDefinition $fieldDef): array
    {
        $info = [
            'type' => $fieldDef->fieldTypeIdentifier,
            'required' => $this->isFieldRequired($fieldDef),
            'translatable' => (bool) $fieldDef->isTranslatable,
        ];

        $description = trim((string) $fieldDef->getDescription());
        if ($description !== '') {
            $info['description'] = $description;
        }

        switch ($fieldDef->fieldTypeIdentifier) {
            case FieldType::EZMATRIX:
                $columns = [];
                foreach ((array) ($fieldDef->fieldSettings['columns'] ?? []) as $column) {
                    $columns[] = [
                        'identifier' => (string) ($column['identifier'] ?? ''),
                        'name' => (string) ($column['name'] ?? ''),
                    ];
                }
                $info['columns'] = $columns;
                $this->appendMatrixRowLimits($info, $fieldDef);
                break;

            case FieldType::EZOBJECTRELATIONLIST:
                $info['allowedTypes'] = array_values(
                    (array) ($fieldDef->fieldSettings['selectionContentTypes'] ?? [])
                );
                $this->appendRelationListItemLimits($info, $fieldDef);
                break;

            case FieldType::EZSELECTION:
                $info['options'] = array_values(
                    (array) ($fieldDef->fieldSettings['options'] ?? [])
                );
                break;

            case FieldType::EZSTRING:
            case FieldType::EZTEXT:
                $this->appendStringConstraints($info, $fieldDef);
                break;

            case FieldType::EZIMAGE:
                $this->appendImageConstraints($info, $fieldDef);
                break;
        }

        return $info;
    }

    /**
     * Determine whether a field is required based on its definition and type.
     *
     * Strategy (in priority order):
     *   1. Trust the content type's isRequired flag (set by Ibexa's mapper).
     *   2. ezimage is always required — there is no "empty" image value.
     *   3. Fall back to validator configuration (StringLength min > 0,
     *      minimum row count, minimum relation limit, etc.).
     */
    private function isFieldRequired(FieldDefinition $fieldDef): bool
    {
        if ($fieldDef->isRequired()) {
            return true;
        }

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZIMAGE) {
            return true;
        }

        $validator = $fieldDef->getValidatorConfiguration();

        if (isset($validator['StringLengthValidator']['minStringLength'])
            && (int) $validator['StringLengthValidator']['minStringLength'] > 0) {
            return true;
        }

        if (isset($validator['MatrixValueValidator']['minimumRowCount'])
            && (int) $validator['MatrixValueValidator']['minimumRowCount'] > 0) {
            return true;
        }

        if (isset($validator['RelationValidator']['minimumRelationLimit'])
            && (int) $validator['RelationValidator']['minimumRelationLimit'] > 0) {
            return true;
        }

        return false;
    }

    /**
     * Build the full field schema map for a content type.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildFieldSchemas(ContentType $contentType): array
    {
        $fields = [];
        foreach ($contentType->fieldDefinitions as $fieldDef) {
            $fields[$fieldDef->identifier] = $this->describeField($fieldDef);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendStringConstraints(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['StringLengthValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        $min = isset($v['minStringLength']) ? (int) $v['minStringLength'] : 0;
        $max = isset($v['maxStringLength']) ? (int) $v['maxStringLength'] : 0;
        if ($min > 0) {
            $info['minLength'] = $min;
        }
        if ($max > 0) {
            $info['maxLength'] = $max;
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendImageConstraints(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration();
        if (isset($v['FileSizeValidator']['maxFileSize'])
            && (int) $v['FileSizeValidator']['maxFileSize'] > 0) {
            $info['maxFileSize'] = (int) $v['FileSizeValidator']['maxFileSize'];
        }
        if (!empty($v['AlternativeTextValidator']['required'])) {
            $info['altTextRequired'] = true;
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendMatrixRowLimits(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['MatrixValueValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        if (isset($v['minimumRowCount']) && (int) $v['minimumRowCount'] > 0) {
            $info['minRows'] = (int) $v['minimumRowCount'];
        }
        if (isset($v['maximumRowCount']) && (int) $v['maximumRowCount'] > 0) {
            $info['maxRows'] = (int) $v['maximumRowCount'];
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function appendRelationListItemLimits(array &$info, FieldDefinition $fieldDef): void
    {
        $v = $fieldDef->getValidatorConfiguration()['RelationValidator'] ?? null;
        if (!is_array($v)) {
            return;
        }
        if (isset($v['minimumRelationLimit']) && (int) $v['minimumRelationLimit'] > 0) {
            $info['minItems'] = (int) $v['minimumRelationLimit'];
        }
        if (isset($v['maximumRelationLimit']) && (int) $v['maximumRelationLimit'] > 0) {
            $info['maxItems'] = (int) $v['maximumRelationLimit'];
        }
    }
}
