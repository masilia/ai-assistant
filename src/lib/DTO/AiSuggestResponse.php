<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

class AiSuggestResponse
{
    public function __construct(
        public readonly string $suggestion,
        public readonly string $format,
    ) {}

    public function toArray(): array
    {
        return [
            'suggestion' => $this->suggestion,
            'format' => $this->format,
        ];
    }
}
