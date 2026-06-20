<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezkeyword fields.
 *
 * Accepts: a comma-separated string or an array of strings.
 * Returns: array of keyword strings.
 */
readonly class KeywordTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezkeyword';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        if (is_array($value)) {
            return array_values(array_map('trim', array_filter($value, 'is_string')));
        }

        return $value;
    }
}
