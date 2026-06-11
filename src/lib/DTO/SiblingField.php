<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

readonly class SiblingField
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
