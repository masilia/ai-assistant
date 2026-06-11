<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;
use Throwable;

/**
 * Fallback stringifier: attempts FieldTypeService::toHash(), then __toString().
 *
 * Registered with the special `_fallback` pseudo-type so the registry uses it
 * when no field-type-specific stringifier matches.
 */
final class GenericStringifier implements FieldValueStringifierInterface
{
    public function __construct(
        private readonly FieldTypeService $fieldTypeService,
    ) {
    }

    public static function getSupportedFieldTypes(): array
    {
        return [FieldValueStringifierInterface::FALLBACK_TYPE];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $value = $field->value;

        if ($value === null) {
            return '';
        }

        try {
            $fieldType = $this->fieldTypeService->getFieldType($fieldDefinition->fieldTypeIdentifier);
            $hash = $fieldType->toHash($value);

            return $this->hashToString($hash);
        } catch (Throwable) {
            if (method_exists($value, '__toString')) {
                return trim((string) $value);
            }

            return '';
        }
    }

    /**
     * Recursively converts a hash (from FieldTypeService::toHash()) into
     * a flat, comma-separated string suitable for AI context.
     *
     * Limitation: nested arrays are flattened to a single level. For
     * example, `['nested' => ['key' => 'value']]` produces `"key: value"`
     * — the `nested` namespace is lost. This is acceptable for the
     * fallback stringifier's purpose (providing context to the LLM);
     * field-type-specific stringifiers handle structured data properly.
     */
    private function hashToString(mixed $hash): string
    {
        if (is_string($hash)) {
            return trim($hash);
        }

        if (is_scalar($hash)) {
            return trim((string) $hash);
        }

        if (is_array($hash)) {
            $parts = [];
            foreach ($hash as $key => $val) {
                $str = $this->hashToString($val);
                if ($str !== '') {
                    $parts[] = is_int($key) ? $str : "$key: $str";
                }
            }

            return implode(', ', $parts);
        }

        return '';
    }
}
