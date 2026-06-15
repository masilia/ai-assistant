<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\SelectionTransformer;

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
            $map[$transformer->getFieldType()] = $transformer;
        }
        $this->transformers = $map;
    }

    /**
     * Transform a raw LLM value for the given field type.
     *
     * If no transformer is registered for the field type, the value is returned unchanged.
     * When a FieldDefinition is provided for an ezselection field, label→index resolution
     * is handled automatically before the transformer runs.
     *
     * @param string      $fieldTypeIdentifier Ibexa field type identifier (e.g. 'ezrichtext')
     * @param string      $fieldIdentifier     Field identifier on the content type
     * @param mixed       $value               Raw value from the LLM
     * @param FieldDefinition|null $fieldDef   Optional field definition for label resolution
     *
     * @return mixed Transformed value ready for setField()
     */
    public function transform(
        string $fieldTypeIdentifier,
        string $fieldIdentifier,
        mixed $value,
        ?FieldDefinition $fieldDef = null,
    ): mixed {
        // Resolve ezselection label → index before the transformer runs
        if ($fieldTypeIdentifier === 'ezselection' && $fieldDef !== null) {
            $value = SelectionTransformer::resolveLabel($fieldDef, $value);
        }

        $transformer = $this->transformers[$fieldTypeIdentifier] ?? null;

        if ($transformer === null) {
            return $value;
        }

        return $transformer->transform($fieldTypeIdentifier, $fieldIdentifier, $value);
    }
}
