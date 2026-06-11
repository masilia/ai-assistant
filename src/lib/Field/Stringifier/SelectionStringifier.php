<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

/**
 * ezselection: options = [0 => 'Label A', 1 => 'Label B', ...],
 *              selection = [0, 2] (selected numeric indices).
 */
final class SelectionStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return [FieldType::EZSELECTION];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $options = $fieldDefinition->fieldSettings['options'] ?? [];
        $selected = $field->value->selection ?? [];
        $labels = [];

        foreach ($selected as $index) {
            if (isset($options[$index])) {
                $labels[] = $options[$index];
            }
        }

        return implode(', ', $labels);
    }
}
