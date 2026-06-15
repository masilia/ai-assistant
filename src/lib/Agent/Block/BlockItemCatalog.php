<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;

final class BlockItemCatalog
{
    private const ITEM_GROUP = 'Block items';
    private const CACHE_KEY = 'masilia_ai.block_item_catalog.available_items';
    private const CACHE_TTL = 3600;

    /** @var array<string, array>|null */
    private ?array $memo = null;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly BlockCatalog $blockCatalog,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get all block item content types.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, string>}>
     */
    public function getAvailableItems(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            return $this->memo = $item->get();
        }

        $items = [];
        try {
            $group = $this->contentTypeService->loadContentTypeGroupByIdentifier(self::ITEM_GROUP);
        } catch (NotFoundException) {
            return $this->memo = [];
        }
        $types = $this->contentTypeService->loadContentTypes($group);

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

        $item->set($items)->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->memo = $items;
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
