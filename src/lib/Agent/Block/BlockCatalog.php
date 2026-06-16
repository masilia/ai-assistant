<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Psr\Cache\CacheItemPoolInterface;

final class BlockCatalog
{
    private const BLOCK_GROUP = 'Blocks';
    private const CACHE_KEY = 'masilia_ai.block_catalog.available_blocks';
    private const CACHE_TTL = 3600; // 1 hour; invalidated by AI cache warmer too

    /**
     * Semantic capabilities mapped to block type identifiers.
     *
     * @var array<string, string[]>
     */
    private const CAPABILITIES = [
        'hero'    => ['hero_banner', 'hero_carousel', 'hero_stats_split', 'hero_images_grid'],
        'text'    => ['paragraph', 'richtext', 'symbol_text'],
        'cards'   => ['grid_cards', 'info_cards', 'category_grid_cards'],
        'media'   => ['image_text', 'slider', 'logo_display'],
        'cta'     => ['cta'],
        'listing' => ['all_posts'],
        'faq'     => ['faq_accordion'],
        'form'    => ['form', 'steps_form'],
        'map'     => ['map', 'interactive_map'],
        'social'  => ['social_networks'],
        'tabs'    => ['tabs'],
        'team'    => ['team_join', 'openings_list'],
        'process' => ['processing_steps', 'checklist_image'],
        'partners'=> ['partners'],
        'data'    => ['chart_set'],
    ];

    /**
     * Request-scoped memoization on top of the PSR-6 pool.
     *
     * @var array<string, array>|null
     */
    private ?array $memo = null;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get all available block content types.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, string>}>
     */
    public function getAvailableBlocks(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            return $this->memo = $item->get();
        }

        $blocks = [];
        foreach ($this->loadBlockTypes() as $type) {
            $fields = [];
            foreach ($type->fieldDefinitions as $fieldDef) {
                $fields[$fieldDef->identifier] = $fieldDef->fieldTypeIdentifier;
            }

            $blocks[$type->identifier] = [
                'identifier' => $type->identifier,
                'name' => $type->getName(),
                'fields' => $fields,
            ];
        }

        $item->set($blocks)->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->memo = $blocks;
    }

    /**
     * Get field definitions for a specific block type.
     *
     * @return array<string, string> field_identifier => field_type_identifier
     */
    public function getBlockFields(string $blockTypeId): array
    {
        $blocks = $this->getAvailableBlocks();

        return $blocks[$blockTypeId]['fields'] ?? [];
    }

    /**
     * Get the block item types (child content types) for a block.
     *
     * @return string[] item type identifiers
     */
    public function getBlockItemTypes(string $blockTypeId): array
    {
        $blocks = $this->loadBlockTypes();
        foreach ($blocks as $type) {
            if ($type->identifier === $blockTypeId) {
                return $this->extractItemTypes($type);
            }
        }

        return [];
    }

    /**
     * Find block types that match a given capability.
     *
     * @return string[] block type identifiers
     */
    public function findBlocksByCapability(string $capability): array
    {
        return self::CAPABILITIES[$capability] ?? [];
    }

    /**
     * Get all capabilities and their block types.
     *
     * @return array<string, string[]>
     */
    public function getCapabilities(): array
    {
        return self::CAPABILITIES;
    }

    /**
     * Resolve a natural language keyword to a capability string.
     */
    public function resolveCapability(string $keyword): ?string
    {
        $keyword = strtolower(trim($keyword));

        foreach (self::CAPABILITIES as $cap => $types) {
            if ($cap === $keyword) {
                return $cap;
            }
            if (in_array($keyword, $types, true)) {
                return $cap;
            }
        }

        return null;
    }

    /**
     * Render a human-readable summary of all block types grouped by capability.
     *
     * Used by both the agent orchestrator (frontend display) and the LLM
     * prompt builder (system prompt context).
     */
    public function renderBlockSummary(): string
    {
        $blocks = $this->getAvailableBlocks();
        $capabilities = $this->getCapabilities();

        $output = '';
        foreach ($capabilities as $cap => $types) {
            $output .= sprintf("\n%s:\n", ucfirst($cap));
            foreach ($types as $type) {
                $info = $blocks[$type] ?? null;
                if ($info === null) {
                    $fields = 'unknown';
                } else {
                    $fieldParts = [];
                    foreach ($info['fields'] as $fieldId => $fieldType) {
                        $fieldParts[] = $fieldId . ':' . $fieldType;
                    }
                    $fields = implode(', ', $fieldParts);
                }
                $output .= sprintf("  - %s (fields: %s)\n", $type, $fields);
            }
        }

        return $output !== '' ? "Available block types and their capabilities:" . rtrim($output, "\n") : '';
    }

    /**
     * @return ContentType[]
     */
    private function loadBlockTypes(): array
    {
        try {
            $group = $this->contentTypeService->loadContentTypeGroupByIdentifier(self::BLOCK_GROUP);
        } catch (NotFoundException $e) {
            return [];
        }

        return array_values(
            $this->contentTypeService->loadContentTypes($group)
        );
    }

    /**
     * Extract item type identifiers from a block type's relation list fields.
     *
     * @return string[]
     */
    private function extractItemTypes(ContentType $type): array
    {
        $itemTypes = [];
        foreach ($type->fieldDefinitions as $fieldDef) {
            $settings = $fieldDef->fieldSettings;
            if (isset($settings['selectionContentTypes']) && is_array($settings['selectionContentTypes'])) {
                foreach ($settings['selectionContentTypes'] as $itemType) {
                    $itemTypes[] = (string) $itemType;
                }
            }
        }

        return array_unique($itemTypes);
    }
}
