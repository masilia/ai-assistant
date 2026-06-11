<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class FileStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZIMAGE, FieldType::EZIMAGEASSET, FieldType::EZBINARYFILE, FieldType::EZMEDIA];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->value;

        if ($value === null || !property_exists($value, 'fileName') || !$value->fileName) {
            return '';
        }

        $fileName = (string) $value->fileName;

        if (property_exists($value, 'alternativeText') && !empty($value->alternativeText)) {
            return $fileName . ' (alt: ' . $value->alternativeText . ')';
        }

        return $fileName;
    }
}
