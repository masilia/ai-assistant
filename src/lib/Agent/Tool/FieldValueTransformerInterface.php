<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

/**
 * Transforms LLM output values into Ibexa-compatible formats for setField().
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
     * @param string $fieldTypeIdentifier Ibexa field type identifier (e.g. 'ezrichtext')
     * @param string $fieldIdentifier     Field identifier on the content type (e.g. 'body')
     * @param mixed  $value               Raw value from the LLM
     *
     * @return mixed Transformed value ready for setField()
     */
    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed;
}
