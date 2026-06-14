<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezkeyword fields.
 *
 * Accepts: a comma-separated string or an array of strings.
 * Returns: array of keyword strings.
 */
readonly class KeywordTransformer implements FieldValueTransformerInterface
{
    public function getFieldType(): string
    {
        return 'ezkeyword';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
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
