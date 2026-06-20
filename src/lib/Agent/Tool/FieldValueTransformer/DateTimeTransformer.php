<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use DateTime;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Throwable;

/**
 * Normalizes LLM output into the format expected by ezdatetime fields.
 *
 * Accepts: an ISO 8601 datetime string (e.g. "2026-06-14T10:00:00").
 * Returns: a DateTime object (Ibexa's createValueFromInput() accepts this).
 */
readonly class DateTimeTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezdatetime';
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
