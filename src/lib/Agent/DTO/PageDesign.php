<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\DTO;

readonly class PageDesign
{
    /**
     * @param BlockDesign[] $blocks
     */
    public function __construct(
        public string $title,
        public string $description = '',
        public array  $blocks = [],
        public string $siteaccess = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            blocks: array_map(
                static fn(array $block) => BlockDesign::fromArray($block),
                $data['blocks'] ?? [],
            ),
            siteaccess: $data['siteaccess'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'siteaccess' => $this->siteaccess,
            'blocks' => array_map(
                static fn(BlockDesign $block) => $block->toArray(),
                $this->blocks,
            ),
        ];
    }
}
