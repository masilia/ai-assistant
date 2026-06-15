<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\DTO;

readonly class BlockDesign
{
    /**
     * @param BlockItemDesign[] $items
     */
    public function __construct(
        public string $blockTypeId,
        public string $capability = '',
        public array  $fields = [],
        public array  $items = [],
        public int    $position = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            blockTypeId: $data['blockTypeId'] ?? $data['block_type_id'] ?? '',
            capability: $data['capability'] ?? '',
            fields: $data['fields'] ?? [],
            items: array_map(
                static fn(array $item) => BlockItemDesign::fromArray($item),
                $data['items'] ?? [],
            ),
            position: $data['position'] ?? 0,
        );
    }

    /**
     * Serialize to the shape consumed by CreatePageStructureTool:
     * {type: string, fields: array}.
     *
     * Items are kept inline inside fields (the LLM already places them under
     * the relation field identifier), so we only forward the fields map.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->blockTypeId,
            'fields' => $this->fields,
        ];
    }
}
