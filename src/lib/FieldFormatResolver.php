<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use InvalidArgumentException;
use Masilia\AiAssistant\Field\FieldType;

class FieldFormatResolver
{
    private const FIELD_FORMAT_MAP = [
        FieldType::EZSTRING     => FieldFormat::PLAIN_TEXT,
        FieldType::EZTEXT       => FieldFormat::TEXT_BLOCK,
        FieldType::EZRICHTEXT   => FieldFormat::HTML,
        FieldType::NOVASEOMETAS => FieldFormat::PLAIN_TEXT,
        FieldType::EZMATRIX     => FieldFormat::JSON,
        FieldType::EZIMAGE      => FieldFormat::PLAIN_TEXT,
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
            'ibexa-field-edit--ezstring'      => FieldType::EZSTRING,
            'ibexa-field-edit--eztext'        => FieldType::EZTEXT,
            'ibexa-field-edit--ezrichtext'    => FieldType::EZRICHTEXT,
            'ibexa-field-edit--novaseometas'  => FieldType::NOVASEOMETAS,
            'ibexa-field-edit--ezmatrix'      => FieldType::EZMATRIX,
            'ibexa-field-edit--ezimage'       => FieldType::EZIMAGE,
        ];
    }
}
