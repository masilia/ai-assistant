<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class CountryStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZCOUNTRY];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $countries = $field->value->countries ?? [];

        return implode(', ', array_column($countries, 'Name'));
    }
}
