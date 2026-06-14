<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Masilia\AiAssistant\Agent\DTO\BlockDesign;
use Masilia\AiAssistant\Agent\DTO\BlockItemDesign;
use Masilia\AiAssistant\Agent\DTO\PageDesign;

readonly class BlockDesigner
{
    public function __construct(
        private BlockCatalog     $blockCatalog,
        private BlockItemCatalog $itemCatalog,
    ) {
    }

    /**
     * Design a page structure from an LLM-parsed response.
     *
     * @param array{title: string, description?: string, blocks?: array<int, array{type: string, fields?: array, item_count?: int}>} $parsed
     */
    public function designPageStructure(array $parsed): PageDesign
    {
        $blocks = [];
        $position = 0;

        foreach ($parsed['blocks'] ?? [] as $blockData) {
            $blockTypeId = $blockData['type'] ?? $blockData['block_type_id'] ?? '';
            if ($blockTypeId === '') {
                continue;
            }

            $capability = $this->resolveCapability($blockTypeId);
            $items = $this->buildBlockItems($blockTypeId, $blockData);

            $blocks[] = new BlockDesign(
                blockTypeId: $blockTypeId,
                capability: $capability,
                fields: $blockData['fields'] ?? [],
                items: $items,
                position: $position++,
            );
        }

        // Order blocks logically: hero first, CTA last, content in between
        $blocks = $this->orderBlocks($blocks);

        return new PageDesign(
            title: $parsed['title'] ?? '',
            description: $parsed['description'] ?? '',
            blocks: $blocks,
            siteaccess: $parsed['siteaccess'] ?? '',
        );
    }

    /**
     * Select appropriate block types based on capability requirements.
     *
     * @return string[] block type identifiers
     */
    public function selectBlocks(array $requirements): array
    {
        $selected = [];
        foreach ($requirements as $requirement) {
            $capability = is_string($requirement) ? $requirement : ($requirement['capability'] ?? '');
            $blocks = $this->blockCatalog->findBlocksByCapability($capability);
            if (!empty($blocks)) {
                // Prefer the first (most common) block type for each capability
                $selected[] = $blocks[0];
            }
        }

        return array_unique($selected);
    }

    /**
     * Order blocks logically: hero first, CTA last, content in between.
     *
     * @param BlockDesign[] $blocks
     * @return BlockDesign[]
     */
    public function orderBlocks(array $blocks): array
    {
        $priority = [
            'hero' => 0,
            'text' => 1,
            'cards' => 2,
            'media' => 3,
            'tabs' => 4,
            'process' => 5,
            'team' => 6,
            'partners' => 7,
            'data' => 8,
            'map' => 9,
            'faq' => 10,
            'form' => 11,
            'social' => 12,
            'listing' => 13,
            'cta' => 14,
        ];

        usort($blocks, static function (BlockDesign $a, BlockDesign $b) use ($priority): int {
            $pa = $priority[$a->capability] ?? 50;
            $pb = $priority[$b->capability] ?? 50;

            return $pa <=> $pb;
        });

        // Reassign positions after sorting
        $ordered = [];
        foreach ($blocks as $i => $block) {
            $ordered[] = new BlockDesign(
                blockTypeId: $block->blockTypeId,
                capability: $block->capability,
                fields: $block->fields,
                items: $block->items,
                position: $i,
            );
        }

        return $ordered;
    }

    private function resolveCapability(string $blockTypeId): string
    {
        $capabilities = $this->blockCatalog->getCapabilities();
        foreach ($capabilities as $cap => $types) {
            if (in_array($blockTypeId, $types, true)) {
                return $cap;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $blockData
     * @return BlockItemDesign[]
     */
    private function buildBlockItems(string $blockTypeId, array $blockData): array
    {
        $itemTypes = $this->blockCatalog->getBlockItemTypes($blockTypeId);
        if (empty($itemTypes)) {
            return [];
        }

        // If the LLM specified item_count, create that many items of the first item type
        $itemCount = $blockData['item_count'] ?? $blockData['items'] ?? null;
        if (is_int($itemCount) && $itemCount > 0 && !empty($itemTypes)) {
            return [
                new BlockItemDesign(
                    itemTypeId: $itemTypes[0],
                    fields: $blockData['item_fields'] ?? [],
                    count: $itemCount,
                ),
            ];
        }

        // If the LLM specified explicit items, use them
        if (isset($blockData['items']) && is_array($blockData['items']) && !is_int($blockData['items'])) {
            $items = [];
            foreach ($blockData['items'] as $itemData) {
                $items[] = BlockItemDesign::fromArray($itemData);
            }

            return $items;
        }

        return [];
    }
}
