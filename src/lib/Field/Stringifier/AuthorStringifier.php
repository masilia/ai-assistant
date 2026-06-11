<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class AuthorStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZAUTHOR];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $authors = $field->value->authors ?? [];
        $names = [];

        foreach ($authors as $author) {
            $name = $author->name ?? '';
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }
}
