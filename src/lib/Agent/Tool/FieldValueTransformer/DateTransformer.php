<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use DateTime;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezdate and ezdatetime fields.
 *
 * Accepts: an ISO 8601 date/datetime string (e.g. "2026-06-14" or "2026-06-14T10:00:00").
 * Returns: a DateTime object (Ibexa's createValueFromInput() accepts this).
 */
readonly class DateTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezdate';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (is_string($value) && $value !== '') {
            try {
                return new DateTime($value);
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
