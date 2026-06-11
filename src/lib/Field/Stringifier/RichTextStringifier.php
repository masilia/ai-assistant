<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class RichTextStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZRICHTEXT];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->value;

        if ($value === null || !property_exists($value, 'xml') || !$value->xml instanceof DOMDocument) {
            return '';
        }

        return trim($value->xml->documentElement?->textContent ?? '');
    }
}
