<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use DateTime;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Throwable;

/**
 * Normalizes LLM output into the format expected by ezdate fields.
 *
 * Accepts: an ISO 8601 date string (e.g. "2026-06-14").
 * Returns: a DateTime object (Ibexa's createValueFromInput() accepts this).
 */
readonly class DateTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezdate';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_string($value) && $value !== '') {
            try {
                return new DateTime($value);
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
