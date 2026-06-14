<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

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
     *
     * @param string $fieldTypeIdentifier Ibexa field type identifier (e.g. 'ezrichtext')
     * @param string $fieldIdentifier     Field identifier on the content type
     * @param mixed  $value               Raw value from the LLM
     *
     * @return mixed Transformed value ready for setField()
     */
    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        $transformer = $this->transformers[$fieldTypeIdentifier] ?? null;

        if ($transformer === null) {
            return $value;
        }

        return $transformer->transform($fieldTypeIdentifier, $fieldIdentifier, $value);
    }
}
