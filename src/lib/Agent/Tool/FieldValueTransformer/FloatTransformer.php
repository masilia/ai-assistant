<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Float\Value as FloatValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Transforms LLM output into Ibexa Float\Value for ezfloat fields.
 *
 * Accepts: float, int, numeric strings.
 * Returns: FloatValue object.
 */
readonly class FloatTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezfloat';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_float($value)) {
            return new FloatValue($value);
        }

        if (is_int($value)) {
            return new FloatValue((float) $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return new FloatValue(null);
            }
            if (is_numeric($trimmed)) {
                return new FloatValue((float) $trimmed);
            }
        }

        if (is_null($value)) {
            return new FloatValue(null);
        }

        return $value;
    }
}
