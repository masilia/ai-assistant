<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\DTO;

readonly class BlockItemDesign
{
    public function __construct(
        public string $itemTypeId,
        public array  $fields = [],
        public int    $count = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            itemTypeId: $data['itemTypeId'] ?? $data['item_type_id'] ?? '',
            fields: $data['fields'] ?? [],
            count: $data['count'] ?? 1,
        );
    }
}
