<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezurl fields.
 *
 * Accepts: a URL string or {link, text} array.
 * Returns: ['link' => '...', 'text' => '...'].
 */
readonly class UrlTransformer implements FieldValueTransformerInterface
{
    public function getFieldType(): string
    {
        return 'ezurl';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (is_string($value)) {
            return ['link' => $value, 'text' => $value];
        }

        if (is_array($value) && isset($value['link'])) {
            $value['text'] = $value['text'] ?? $value['link'];

            return $value;
        }

        return $value;
    }
}
