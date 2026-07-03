<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeMatrix\FieldType\Value\Row;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezmatrix fields.
 *
 * Ibexa's matrix field type requires an array of {@see Row} objects. The LLM
 * typically outputs a flat array of row objects, which we wrap into Row instances.
 *
 * Ibexa's {@see Row::isEmpty()} calls `trim()` on every cell value, so all
 * cell values MUST be strings. The LLM sometimes ships arrays (e.g. a list
 * of bullet points, a multi-line text block as an array of lines) inside a
 * matrix cell. Those values are normalised here to a single string before
 * the Row is constructed — arrays are JSON-encoded, other scalars are
 * cast to string, and nulls become the empty string.
 *
 * Fallback: if the LLM wraps the entire row set in a single cell whose value
 * is a JSON string (e.g. `[{item: "[{\"text\":\"...\",...}]"}]`), the
 * transformer detects this, decodes the JSON, and uses the decoded array as
 * the actual rows — mapping values onto the column identifiers from the
 * field definition.
 *
 * Accepts (any of):
 *   - Flat array of row objects:        [{col1: "val1", col2: "val2"}, ...]
 *   - Already-wrapped Row objects:      [new Row({...}), ...]
 *   - Single row object:                {col1: "val1"}
 *   - Nested in 'rows' key:             { rows: [...] }
 *   - JSON-wrapped in single cell:      [{item: "[{col1:...},{...}]"}]
 *
 * Returns: array<Row>
 */
readonly class MatrixTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezmatrix';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        // Already an array of Row objects — pass through (they were sanitised upstream)
        if (is_array($value) && $value !== [] && $this->isAllRows($value)) {
            return array_values($value);
        }

        // Nested: { rows: [{...}, {...}] }
        if (is_array($value) && isset($value['rows']) && is_array($value['rows'])) {
            return $this->rowsFromArray($value['rows']);
        }

        // Flat array of row objects: [{col1: val1}, {col2: val2}]
        if (is_array($value) && array_is_list($value)) {
            $unwrapped = $this->tryUnwrapJsonRows($fieldDef, $value);
            if ($unwrapped !== null) {
                return $this->rowsFromArray($unwrapped);
            }
            return $this->rowsFromArray($value);
        }

        // Single row object: {col1: val1, col2: val2}
        if (is_array($value)) {
            $unwrapped = $this->tryUnwrapJsonRows($fieldDef, [$value]);
            if ($unwrapped !== null) {
                return $this->rowsFromArray($unwrapped);
            }
            return $this->rowsFromArray([$value]);
        }

        return $value;
    }

    /**
     * @param array<int, mixed> $items
     * @return list<Row>
     */
    private function rowsFromArray(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if ($item instanceof Row) {
                $rows[] = $item;
                continue;
            }
            if (is_array($item)) {
                $rows[] = new Row($this->sanitizeCells($item));
            }
        }
        return $rows;
    }

    /**
     * Normalise every cell value to a string. Row::isEmpty() runs `trim()`
     * on each cell and would TypeError on arrays / non-strings.
     *
     * @param array<string, mixed> $cells
     * @return array<string, string>
     */
    private function sanitizeCells(array $cells): array
    {
        return array_map(function ($cell) {
            return $this->stringifyCell($cell);
        }, $cells);
    }

    private function stringifyCell(mixed $cell): string
    {
        if (is_string($cell)) {
            return $cell;
        }
        if ($cell === null || $cell === false) {
            return '';
        }
        if (is_array($cell)) {
            // List of strings -> join with newline; arbitrary array -> JSON.
            $isList = array_is_list($cell);
            $allScalars = $isList && array_reduce(
                $cell,
                static fn($carry, $v) => $carry && (is_string($v) || is_scalar($v) || $v === null),
                true,
            );
            if ($isList && $allScalars) {
                return implode("\n", array_map(
                    static fn($v) => $v === null ? '' : (string) $v,
                    $cell,
                ));
            }
            return (string) json_encode($cell, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        return (string) $cell;
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function isAllRows(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof Row) {
                return false;
            }
        }
        return $value !== [];
    }

    /**
     * Detect and unwrap the common LLM mistake of wrapping all rows inside a
     * single cell as a JSON string.
     *
     * Example: the field has columns [text, url, style] but the LLM sends:
     *   [{item: "[{\"text\":\"Click\",\"url\":\"/page\",\"style\":\"btn-primary\"}]"}]
     *
     * This method checks if every row has exactly one cell whose value is a
     * JSON string that decodes to a list of objects. If so, it returns the
     * decoded list; otherwise returns null.
     *
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>|null
     */
    private function tryUnwrapJsonRows(FieldDefinition $fieldDef, array $rows): ?array
    {
        $columnIds = $this->extractColumnIdentifiers($fieldDef);

        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 1) {
                return null;
            }

            $cellValue = array_values($row)[0];
            if (!is_string($cellValue)) {
                return null;
            }

            $decoded = json_decode($cellValue, true);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                return null;
            }

            // Every decoded element must be an array (row object)
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    return null;
                }
            }

            // If we have column identifiers, validate that the decoded rows
            // use at least one of them — this avoids false positives.
            if ($columnIds !== []) {
                $hasValidColumn = false;
                foreach ($decoded as $item) {
                    foreach (array_keys($item) as $key) {
                        if (in_array($key, $columnIds, true)) {
                            $hasValidColumn = true;
                            break 2;
                        }
                    }
                }
                if (!$hasValidColumn) {
                    return null;
                }
            }

            return $decoded;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractColumnIdentifiers(FieldDefinition $fieldDef): array
    {
        $columns = (array) ($fieldDef->fieldSettings['columns'] ?? []);
        $ids = [];
        foreach ($columns as $column) {
            if (isset($column['identifier'])) {
                $ids[] = (string) $column['identifier'];
            }
        }
        return $ids;
    }
}
