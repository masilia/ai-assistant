<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

readonly class AiSuggestRequest
{
    /**
     * @param string[] $siblingFields
     * @param string[] $metaKeys For multi-value fields (e.g. novaseometas):
     *                                the exact set of editable, AI-eligible meta
     *                                keys present on the form. Drives the
     *                                whole-block schema so it matches the UI.
     */
    public function __construct(
        public string $fieldType,
        public string $prompt,
        public string $currentValue = '',
        public string $fieldName = '',
        public string $language = 'en',
        public int    $contentId = 0,
        public string $contentTitle = '',
        public string $contentType = '',
        public array  $siblingFields = [],
        public string $sourceLanguage = '',
        public string $subFieldKey = '',
        public array  $metaKeys = [],
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            fieldType: $data['fieldType'] ?? '',
            prompt: trim($data['prompt'] ?? ''),
            currentValue: $data['currentValue'] ?? '',
            fieldName: $data['fieldName'] ?? '',
            language: $data['language'] ?? 'en',
            contentId: self::toIntOrZero($data['contentId'] ?? null),
            contentTitle: $data['contentTitle'] ?? '',
            contentType: $data['contentType'] ?? '',
            siblingFields: $data['siblingFields'] ?? [],
            sourceLanguage: $data['sourceLanguage'] ?? '',
            subFieldKey: trim((string)($data['subFieldKey'] ?? '')),
            // Strip empty/non-string values so the AI schema is restricted to real keys.
            metaKeys: array_values(array_filter(
                (array)($data['metaKeys'] ?? []),
                static fn($value) => is_string($value) && $value !== ''
            )),
        );
    }

    private static function toIntOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
