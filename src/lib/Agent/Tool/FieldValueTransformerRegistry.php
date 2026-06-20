<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Registry of field value transformers keyed by Ibexa field type identifier.
 *
 * Falls back to passthrough when no transformer is registered for a type.
 */
readonly class FieldValueTransformerRegistry
{
    /** @var array<string, FieldValueTransformerInterface> */
    private array $transformers;

    /**
     * @param iterable<FieldValueTransformerInterface> $transformers Tagged services
     */
    public function __construct(
        iterable $transformers,
    ) {
        $map = [];
        foreach ($transformers as $transformer) {
            $map[$transformer->getFieldTypeIdentifier()] = $transformer;
        }
        $this->transformers = $map;
    }

    /**
     * Transform a raw LLM value using the transformer registered for this field's type.
     *
     * Falls back to passthrough when no transformer is registered for the field type.
     * The FieldDefinition is forwarded to the transformer so label-resolution
     * transformers (ezselection) can use field settings.
     *
     * @param FieldDefinition $fieldDef Field definition (drives dispatch and provides context)
     * @param mixed           $value    Raw value from the LLM
     *
     * @return mixed Transformed value ready for setField()
     */
    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        $type = $fieldDef->getFieldTypeIdentifier();
        $transformer = $this->transformers[$type] ?? null;

        if ($transformer === null) {
            return $value;
        }

        return $transformer->transform($fieldDef, $value);
    }
}
