<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Masilia\AiAssistant\Field\FieldType;

/**
 * Maps ezselection label strings to option indices.
 *
 * The LLM typically outputs the human-readable label (e.g. "Dark"),
 * but Ibexa's ezselection expects an array of integer indices.
 *
 * Uses the field definition's option list (field settings) to resolve
 * labels to their index values.
 */
readonly class SelectionTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return FieldType::EZSELECTION;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        // Already an array of indices — pass through
        if (is_array($value)) {
            return $value;
        }

        // Single integer index — wrap in array
        if (is_int($value)) {
            return [$value];
        }

        // String label — resolve against the field's option list
        // Options is an indexed array of labels: ['Dark', 'Light', 'Auto']
        if (is_string($value)) {
            $options = $fieldDef->getFieldSettings()['options'] ?? [];
            $index = array_search($value, $options, true);

            if ($index !== false) {
                return [$index];
            }

            // Label not found — return empty array so Ibexa gets a valid Selection\Value
            return [];
        }

        return [];
    }
}
