<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeMatrix\FieldType\Value;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final class MatrixStringifier implements FieldValueStringifierInterface
{
    private const MAX_ROWS = 10;

    public static function getSupportedFieldTypes(): array
    {
        return ['ezmatrix'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        /** @var Value $value */
        $value = $field->value;
        if (!($value instanceof Value)) {
            return '';
        }

        $lines = [];
        $count = 0;

        foreach ($value->getRows() as $row) {
            if (++$count > self::MAX_ROWS) {
                break;
            }
            $lines[] = implode(' | ', $row->getCells());
        }

        return implode("\n", $lines);
    }
}
