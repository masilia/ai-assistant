<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use InvalidArgumentException;

class FieldFormatResolver
{
    private const FIELD_FORMAT_MAP = [
        'ezstring' => FieldFormat::PLAIN_TEXT,
        'eztext' => FieldFormat::TEXT_BLOCK,
        'ezrichtext' => FieldFormat::HTML,
        'novaseometas' => FieldFormat::PLAIN_TEXT,
        'ezmatrix' => FieldFormat::JSON,
    ];

    public function resolve(string $fieldTypeIdentifier): FieldFormat
    {
        return self::FIELD_FORMAT_MAP[$fieldTypeIdentifier]
            ?? throw new InvalidArgumentException(
                sprintf('Unsupported field type for AI suggestion: "%s"', $fieldTypeIdentifier)
            );
    }

    public function supports(string $fieldTypeIdentifier): bool
    {
        return isset(self::FIELD_FORMAT_MAP[$fieldTypeIdentifier]);
    }

    /**
     * @return array<string, string> [cssClass => fieldTypeIdentifier]
     */
    public function getSupportedFieldTypes(): array
    {
        return [
            'ibexa-field-edit--ezstring' => 'ezstring',
            'ibexa-field-edit--eztext' => 'eztext',
            'ibexa-field-edit--ezrichtext' => 'ezrichtext',
            'ibexa-field-edit--novaseometas' => 'novaseometas',
            'ibexa-field-edit--ezmatrix' => 'ezmatrix',
        ];
    }
}
