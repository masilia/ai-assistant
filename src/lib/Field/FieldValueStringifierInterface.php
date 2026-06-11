<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Converts a single Ibexa field value into a compact plain-text string
 * suitable for AI prompt context.
 *
 * Each implementation declares which field-type identifiers it handles via
 * {@see getSupportedFieldTypes()}. The {@see FieldValueStringifierRegistry}
 * indexes them for O(1) dispatch.
 */
interface FieldValueStringifierInterface
{
    /**
     * Pseudo field-type identifier used by the registry to register the
     * one stringifier that handles every other field type
     * (typically {@see GenericStringifier}). It is not a real Ibexa
     * field type — it's a routing sentinel for the registry.
     */
    public const FALLBACK_TYPE = '_fallback';

    /**
     * Field-type identifiers this stringifier handles (e.g. ['ezstring', 'eztext']).
     *
     * @return string[]
     */
    public static function getSupportedFieldTypes(): array;

    /**
     * Returns a plain-text representation of the field value, or '' if empty.
     */
    public function toString(Field $field, FieldDefinition $fieldDefinition): string;
}
