<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeMatrix\FieldType\Value;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class MatrixStringifier implements FieldValueStringifierInterface
{
    private const MAX_ROWS = 10;

    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZMATRIX];
    }

    /**
     * Extract column headers from an ezmatrix field definition's settings.
     *
     * @return array<string, string> identifier => display name
     */
    public static function extractColumnHeaders(FieldDefinition $fieldDefinition): array
    {
        $columns = $fieldDefinition->getFieldSettings()['columns'] ?? [];
        $headers = [];
        foreach ($columns as $col) {
            if (!isset($col['identifier'])) {
                continue;
            }
            $headers[(string)$col['identifier']] = (string)($col['name'] ?? $col['identifier']);
        }

        return $headers;
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        /** @var Value $value */
        $value = $field->value;
        if (!($value instanceof Value)) {
            return '';
        }

        $headerByIdentifier = self::extractColumnHeaders($fieldDefinition);

        $blocks = [];
        $count = 0;
        $rowIndex = 0;

        foreach ($value->getRows() as $row) {
            if (++$count > self::MAX_ROWS) {
                break;
            }
            $rowIndex++;

            $cellLines = [];
            foreach ($row->getCells() as $colId => $cellValue) {
                $colName = $headerByIdentifier[$colId] ?? $colId;
                $trimmed = trim((string)$cellValue);
                if ($trimmed === '') {
                    continue;
                }
                $cellLines[] = sprintf('  - [%s]: %s', $colName, $trimmed);
            }

            if (empty($cellLines)) {
                continue;
            }

            $blocks[] = sprintf('Row %d:', $rowIndex) . "\n" . implode("\n", $cellLines);
        }

        return implode("\n\n", $blocks);
    }
}
