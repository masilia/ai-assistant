<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;

class BlockItemCatalog
{
    private const ITEM_GROUP = 'Block items';

    /** @var array<string, array>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly BlockCatalog      $blockCatalog,
    ) {
    }

    /**
     * Get all block item content types.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, string>}>
     */
    public function getAvailableItems(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $items = [];
        $group = $this->contentTypeService->loadContentTypeGroupByIdentifier(self::ITEM_GROUP);
        $types = $this->contentTypeService->loadContentTypesByIdentifiers([], $group->id);

        foreach ($types as $type) {
            $fields = [];
            foreach ($type->fieldDefinitions as $fieldDef) {
                $fields[$fieldDef->identifier] = $fieldDef->fieldTypeIdentifier;
            }

            $items[$type->identifier] = [
                'identifier' => $type->identifier,
                'name' => $type->name,
                'fields' => $fields,
            ];
        }

        $this->cache = $items;

        return $items;
    }

    /**
     * Get item types that belong to a specific block type.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, string>}>
     */
    public function getItemsForBlock(string $blockTypeId): array
    {
        $itemTypeIds = $this->blockCatalog->getBlockItemTypes($blockTypeId);
        $allItems = $this->getAvailableItems();

        return array_filter(
            $allItems,
            static fn(array $item) => in_array($item['identifier'], $itemTypeIds, true),
        );
    }

    /**
     * Get field definitions for a specific block item type.
     *
     * @return array<string, string> field_identifier => field_type_identifier
     */
    public function getItemFields(string $itemTypeId): array
    {
        $items = $this->getAvailableItems();

        return $items[$itemTypeId]['fields'] ?? [];
    }
}
