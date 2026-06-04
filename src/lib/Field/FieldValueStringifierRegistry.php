<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Dispatches {@see FieldValueStringifierInterface::toString()} by field-type
 * identifier. Mirrors the tagged-iterator registry pattern used by the
 * provider-adapter system and the app's FieldValueTransformer.
 *
 * When no specific stringifier is registered for a field type, delegates to
 * the {@see GenericStringifier} (which must be registered with type `_fallback`).
 */
class FieldValueStringifierRegistry
{
    /** @var array<string, FieldValueStringifierInterface> */
    private array $map = [];

    private ?FieldValueStringifierInterface $fallback = null;

    /**
     * @param iterable<FieldValueStringifierInterface> $stringifiers
     */
    public function __construct(iterable $stringifiers)
    {
        foreach ($stringifiers as $stringifier) {
            foreach ($stringifier::getSupportedFieldTypes() as $type) {
                if ($type === '_fallback') {
                    $this->fallback = $stringifier;
                    continue;
                }
                $this->map[$type] = $stringifier;
            }
        }
    }

    /**
     * Converts a field value to a plain-text string for AI context.
     *
     * Returns '' when neither a specific nor a fallback stringifier can
     * produce output.
     */
    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        $type = $fieldDefinition->fieldTypeIdentifier;
        $stringifier = $this->map[$type] ?? $this->fallback;

        if ($stringifier === null) {
            return '';
        }

        return $stringifier->toString($field, $fieldDefinition);
    }

    /**
     * Whether a specific (non-fallback) stringifier is registered for the
     * given field type.
     */
    public function hasStringifier(string $fieldTypeIdentifier): bool
    {
        return isset($this->map[$fieldTypeIdentifier]);
    }
}
