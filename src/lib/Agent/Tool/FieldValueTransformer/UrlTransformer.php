<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Url\Value as UrlValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezurl fields.
 *
 * Accepts: a URL string or {link, text} array.
 * Returns: UrlValue object.
 */
readonly class UrlTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezurl';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if ($value instanceof UrlValue) {
            return $value;
        }

        if (is_string($value)) {
            return new UrlValue($value, $value);
        }

        if (is_array($value) && isset($value['link'])) {
            return new UrlValue($value['link'], $value['text'] ?? $value['link']);
        }

        return $value;
    }
}
