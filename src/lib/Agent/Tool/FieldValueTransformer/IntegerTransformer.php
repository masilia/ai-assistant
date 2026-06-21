<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Integer\Value as IntegerValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Transforms LLM output into Ibexa Integer\Value for ezinteger fields.
 *
 * Accepts: int, float, numeric strings.
 * Returns: IntegerValue object.
 */
readonly class IntegerTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezinteger';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_int($value)) {
            return new IntegerValue($value);
        }

        if (is_float($value)) {
            return new IntegerValue((int) $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return new IntegerValue(null);
            }
            if (is_numeric($trimmed)) {
                return new IntegerValue((int) $trimmed);
            }
        }

        if (is_null($value)) {
            return new IntegerValue(null);
        }

        return $value;
    }
}
