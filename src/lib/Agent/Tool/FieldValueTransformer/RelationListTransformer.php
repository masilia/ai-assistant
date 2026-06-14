<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezobjectrelationlist fields.
 *
 * Accepts: array of content IDs or ['destinationContentIds' => [...]].
 * Returns: array of content IDs ( Ibexa accepts a flat array for relation lists).
 */
readonly class RelationListTransformer implements FieldValueTransformerInterface
{
    public function getFieldType(): string
    {
        return 'ezobjectrelationlist';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Already wrapped: ['destinationContentIds' => [...]]
        if (isset($value['destinationContentIds']) && is_array($value['destinationContentIds'])) {
            return $value['destinationContentIds'];
        }

        // Flat array of integers — pass through
        return $value;
    }
}
