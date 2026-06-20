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
     * Always returns at minimum `{type, required}`. Adds `columns` for
     * ezmatrix fields and `allowedTypes` for ezobjectrelationlist fields.
     *
     * @return array<string, mixed>
     */
    private function describeField(FieldDefinition $fieldDef): array
    {
        $info = [
            'type' => $fieldDef->fieldTypeIdentifier,
            'required' => $this->isFieldRequired($fieldDef),
        ];

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZMATRIX) {
            $columns = [];
            foreach ((array) ($fieldDef->fieldSettings['columns'] ?? []) as $column) {
                $columns[] = [
                    'identifier' => (string) ($column['identifier'] ?? ''),
                    'name' => (string) ($column['name'] ?? ''),
                ];
            }
            $info['columns'] = $columns;
        }

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZOBJECTRELATIONLIST) {
            $info['allowedTypes'] = array_values(
                (array) ($fieldDef->fieldSettings['selectionContentTypes'] ?? [])
            );
        }

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZSELECTION) {
            $info['options'] = array_values(
                (array) ($fieldDef->fieldSettings['options'] ?? [])
            );
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

        if ($fieldDef->fieldTypeIdentifier === 'ezimage') {
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
}
