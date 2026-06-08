<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\AiAssistant\AiConstants;
use Masilia\AiAssistant\DTO\SiblingField;

/**
 * Walks every other field in the same content item and produces a list of
 * SiblingField value objects (label + truncated string value) for AI
 * context. Delegates the actual value → string conversion to the
 * FieldValueStringifierRegistry.
 *
 * Pure orchestration: no I/O, no logging, no exception swallowing.
 */
class SiblingFieldsExtractor
{
    public function __construct(
        private readonly FieldValueStringifierRegistry $stringifierRegistry,
    ) {
    }

    /**
     * @return SiblingField[]
     */
    public function extract(
        Content     $content,
        ContentType $contentType,
        string      $currentFieldIdentifier,
        string      $language,
    ): array {
        $siblingFields = [];

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            $identifier = $fieldDef->identifier;

            if ($identifier === $currentFieldIdentifier) {
                continue;
            }

            $field = $content->getField($identifier, $language)
                ?? $content->getField($identifier);

            if ($field === null) {
                continue;
            }

            $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);
            if ($stringValue === '') {
                continue;
            }

            $label = $fieldDef->getName() ?: $identifier;

            $siblingFields[] = new SiblingField(
                label: $label,
                value: mb_substr($stringValue, 0, AiConstants::MAX_SIBLING_CHARS),
            );
        }

        return $siblingFields;
    }
}
