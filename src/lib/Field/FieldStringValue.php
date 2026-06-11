<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

/**
 * A string field's value together with its human-readable label.
 *
 * Returned by {@see FieldContextExtractor::getFieldValueInLanguage()} to
 * replace the loose `array{value: string, label: string}` shape.
 */
final readonly class FieldStringValue
{
    public function __construct(
        public string $value,
        public string $label,
    ) {
    }
}
