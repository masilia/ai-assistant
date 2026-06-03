<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

class AiSuggestRequest
{
    public function __construct(
        public readonly string $fieldType,
        public readonly string $prompt,
        public readonly string $currentValue = '',
        public readonly string $fieldName = '',
        public readonly string $language = 'en',
        public readonly int $contentId = 0,
        public readonly string $contentTitle = '',
        public readonly string $contentType = '',
        public readonly array $siblingFields = [],
        public readonly string $sourceLanguage = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fieldType: $data['fieldType'] ?? '',
            prompt: trim($data['prompt'] ?? ''),
            currentValue: $data['currentValue'] ?? '',
            fieldName: $data['fieldName'] ?? '',
            language: $data['language'] ?? 'en',
            contentId: isset($data['contentId']) && $data['contentId'] !== '' ? (int)$data['contentId'] : 0,
            contentTitle: $data['contentTitle'] ?? '',
            contentType: $data['contentType'] ?? '',
            siblingFields: $data['siblingFields'] ?? [],
            sourceLanguage: $data['sourceLanguage'] ?? '',
        );
    }
}
