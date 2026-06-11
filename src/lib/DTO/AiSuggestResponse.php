<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

readonly class AiSuggestResponse
{
    public function __construct(
        public string $suggestion,
        public string $format,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'suggestion' => $this->suggestion,
            'format' => $this->format,
        ];
    }
}
