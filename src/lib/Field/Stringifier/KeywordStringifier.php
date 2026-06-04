<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class KeywordStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return ['ezkeyword'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        return implode(', ', $field->value->values ?? []);
    }
}
