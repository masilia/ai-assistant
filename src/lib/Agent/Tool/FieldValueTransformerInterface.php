<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Transforms LLM output values into Ibexa-compatible formats for setField().
 *
 * The FieldDefinition carries everything a transformer might need: the field
 * type identifier ({@see FieldDefinition::getFieldTypeIdentifier()}) and the
 * field identifier ({@see FieldDefinition::identifier}), plus settings for
 * transformers that need configuration context (e.g. SelectionTransformer
 * resolving labels against the field's option list).
 */
interface FieldValueTransformerInterface
{
    /**
     * Return the Ibexa field type identifier this transformer handles.
     *
     * @return string e.g. 'ezrichtext', 'ezmatrix'
     */
    public function getFieldTypeIdentifier(): string;

    /**
     * Transform a raw LLM value into the format expected by the given field type.
     *
     * @param FieldDefinition $fieldDef Field definition (carries type, identifier, settings)
     * @param mixed           $value    Raw value from the LLM
     *
     * @return mixed Transformed value ready for setField()
     */
    public function transform(FieldDefinition $fieldDef, mixed $value): mixed;
}
