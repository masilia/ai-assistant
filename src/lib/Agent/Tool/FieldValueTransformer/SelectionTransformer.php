<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Maps ezselection label strings to option indices.
 *
 * The LLM typically outputs the human-readable label (e.g. "Dark"),
 * but Ibexa's ezselection expects an array of integer indices.
 *
 * This transformer requires the FieldDefinition to resolve labels.
 * It is invoked by the FieldValueTransformerRegistry, but because the
 * interface does not pass the FieldDefinition, the actual label→index
 * resolution must be performed by the caller before reaching the registry.
 *
 * This transformer handles the array-wrapping part: if the value is
 * already an integer, it wraps it in an array.
 */
readonly class SelectionTransformer implements FieldValueTransformerInterface
{
    public function getFieldType(): string
    {
        return 'ezselection';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        // Already an array of indices — pass through
        if (is_array($value)) {
            return $value;
        }

        // Single integer index — wrap in array
        if (is_int($value)) {
            return [$value];
        }

        // String label — cannot resolve without FieldDefinition context,
        // return as-is (caller must resolve via resolveLabel() first)
        return $value;
    }

    /**
     * Resolve a label string to its option index using field settings.
     *
     * Call this before passing through the transformer registry.
     *
     * @return int[]|mixed Resolved index array, or original value if unresolvable
     */
    public static function resolveLabel(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $options = $fieldDef->getFieldSettings()['options'] ?? [];
        $labelToIndex = array_flip($options);
        $index = $labelToIndex[$value] ?? null;

        if ($index !== null) {
            return [$index];
        }

        return $value;
    }
}
