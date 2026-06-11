<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class TagsStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZTAGS];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->value;

        if (!property_exists($value, 'tags') || !is_iterable($value->tags)) {
            return '';
        }

        $keywords = [];
        foreach ($value->tags as $tag) {
            $keyword = $tag->getKeyword() ?? '';
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return implode(', ', $keywords);
    }
}
