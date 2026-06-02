<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\DTO;

class SiblingField
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
