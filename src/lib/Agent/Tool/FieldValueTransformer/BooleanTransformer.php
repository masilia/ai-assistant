<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Checkbox\Value as CheckboxValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Transforms LLM output into Ibexa Checkbox\Value for ezboolean fields.
 *
 * Accepts: bool, "true"/"false" strings, 0/1 integers.
 * Returns: CheckboxValue object.
 */
readonly class BooleanTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ezboolean';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (is_bool($value)) {
            return new CheckboxValue($value);
        }

        if (is_int($value)) {
            return new CheckboxValue($value !== 0);
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['true', '1', 'yes'], true)) {
                return new CheckboxValue(true);
            }
            if (in_array($lower, ['false', '0', 'no', ''], true)) {
                return new CheckboxValue(false);
            }
        }

        return new CheckboxValue(false);
    }
}
