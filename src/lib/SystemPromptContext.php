<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Immutable bag of all the inputs {@see AiPromptBuilder::buildSystemPrompt()}
 * needs. Replaces a 10-parameter call signature with a single, named-fields
 * value object that is easy to construct, easy to mock in tests, and
 * forwards-compatible (new fields can be added without breaking callers).
 */
final readonly class SystemPromptContext
{
    /**
     * @param array<int, array{label: string, value: string}> $siblingFields
     * @param string[] $metaKeys For novaseometas: the AI-eligible meta keys
     */
    public function __construct(
        public FieldFormat $format,
        public string      $fieldName = '',
        public string      $contentType = '',
        public string      $language = 'en',
        public string      $contentTitle = '',
        public array       $siblingFields = [],
        public string      $fieldType = '',
        public string      $subFieldKey = '',
        public array       $metaKeys = [],
    )
    {
    }
}
