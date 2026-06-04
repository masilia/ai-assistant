<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class FileStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return ['ezimage', 'ezimageasset', 'ezbinaryfile', 'ezmedia'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->value;

        if ($value === null || !property_exists($value, 'fileName') || !$value->fileName) {
            return '';
        }

        return (string) $value->fileName;
    }
}
