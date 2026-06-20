<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezobjectrelation fields.
 *
 * Accepts: integer (content ID) or ['destinationContentId' => int].
 * Returns: ['destinationContentId' => int] or null.
 */
readonly class RelationTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezobjectrelation';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Raw integer content ID
        if (is_int($value)) {
            return ['destinationContentId' => $value];
        }

        // Already in correct format
        if (is_array($value) && isset($value['destinationContentId'])) {
            return $value;
        }

        return $value;
    }
}
