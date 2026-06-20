<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Masilia\AiAssistant\Field\FieldType;

/**
 * Normalizes LLM output into the format expected by ezobjectrelationlist fields.
 *
 * Accepts: array of content IDs or ['destinationContentIds' => [...]].
 * Returns: array of content IDs ( Ibexa accepts a flat array for relation lists).
 */
readonly class RelationListTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return FieldType::EZOBJECTRELATIONLIST;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Already wrapped: ['destinationContentIds' => [...]]
        if (isset($value['destinationContentIds']) && is_array($value['destinationContentIds'])) {
            return $value['destinationContentIds'];
        }

        // Flatten nested arrays (e.g. [[1176], [1177]] → [1176, 1177])
        $flat = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                array_push($flat, ...$entry);
            } else {
                $flat[] = $entry;
            }
        }

        return $flat;
    }
}
