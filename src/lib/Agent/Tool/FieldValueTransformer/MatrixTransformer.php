<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezmatrix fields.
 *
 * Expected format from LLM:
 *   { "rows": [ { "cells": { "col1": "val1", "col2": "val2" } } ] }
 *
 * Also handles a flat array of objects:
 *   [ { "col1": "val1" }, { "col2": "val2" } ]
 */
readonly class MatrixTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezmatrix';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Already in correct format: { rows: [{ cells: {...} }] }
        if (isset($value['rows']) && is_array($value['rows'])) {
            return $value;
        }

        // Flat array of row objects: [{ col1: val1 }, { col2: val2 }]
        if (array_is_list($value)) {
            $rows = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = ['cells' => $item];
            }

            return ['rows' => $rows];
        }

        // Single object: { col1: val1, col2: val2 }
        return ['rows' => [['cells' => $value]]];
    }
}
