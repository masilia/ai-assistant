<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldType;
use Psr\Cache\CacheItemPoolInterface;

final class BlockCatalog
{
    private const BLOCK_GROUP = 'Blocks';
    private const CACHE_KEY = 'masilia_ai.block_catalog.available_blocks.v2';
    private const CACHE_TTL = 3600; // 1 hour; invalidated by AI cache warmer too

    /**
     * Request-scoped memoization on top of the PSR-6 pool.
     *
     * @var array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}>|null
     */
    private ?array $memo = null;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get all available block content types with detailed per-field schemas.
     *
     * Each block entry is shaped as:
     *   {
     *     identifier: 'info_cards',
     *     name: 'Info Cards',
     *     fields: {
     *       cards: {type: 'ezmatrix', required: true, columns: [{identifier: 'icon', name: 'Icon'}, ...]},
     *       items: {type: 'ezobjectrelationlist', required: false, allowedTypes: ['card_item']},
     *       title: {type: 'ezstring', required: false}
     *     }
     *   }
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}>
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
                $fields[$fieldDef->identifier] = $this->describeField($fieldDef);
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
     * Get the full schema for a single block type (or null if unknown).
     *
     * @return array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null
     */
    public function getBlockSchema(string $blockTypeId): ?array
    {
        return $this->getAvailableBlocks()[$blockTypeId] ?? null;
    }

    /**
     * Render a flat list of installed block types with their field schemas.
     *
     * Lists only blocks that are actually installed in Ibexa — the LLM
     * derives semantic grouping (hero / text / cards / etc.) at planning
     * time. Matrix column identifiers and relation-list allowed types
     * are inlined so the LLM can produce correct plan arguments.
     *
     * Example output:
     *   Available block types:
     *     - hero_banner (title:ezstring, subtitle:ezstring, image:ezimage)
     *     - info_cards (cards:ezmatrix[icon,title,body], items:ezobjectrelationlist<card_item>)
     */
    public function renderBlockSummary(): string
    {
        $blocks = $this->getAvailableBlocks();
        if ($blocks === []) {
            return '';
        }

        $lines = ['Available block types:'];
        foreach ($blocks as $info) {
            $identifier = (string) $info['identifier'];
            if ($identifier === '') {
                continue;
            }
            $lines[] = sprintf('  - %s (%s)', $identifier, $this->formatFields($info['fields']));
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function formatFields(array $fields): string
    {
        $parts = [];
        foreach ($fields as $fieldId => $fieldInfo) {
            $typeStr = (string) ($fieldInfo['type'] ?? '');
            $suffix = $this->fieldSuffix($fieldInfo);
            $parts[] = $fieldId . ':' . $typeStr . $suffix;
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $fieldInfo
     */
    private function fieldSuffix(array $fieldInfo): string
    {
        $type = (string) ($fieldInfo['type'] ?? '');
        if ($type === FieldType::EZMATRIX && !empty($fieldInfo['columns'])) {
            $cols = array_column($fieldInfo['columns'], 'identifier');

            return '[' . implode(',', $cols) . ']';
        }
        if ($type === FieldType::EZOBJECTRELATIONLIST && !empty($fieldInfo['allowedTypes'])) {
            return '<' . implode('|', $fieldInfo['allowedTypes']) . '>';
        }

        return '';
    }

    /**
     * Build a detailed schema entry for a single field definition.
     *
     * Always returns at minimum `{type, required}`. Adds `columns` for
     * ezmatrix fields and `allowedTypes` for ezobjectrelationlist fields.
     *
     * Required detection: trusts $fieldDef->isRequired() (set by the
     * Ibexa ContentTypeDomainMapper from the database). Adds fallback
     * rules for field types that are intrinsically required (ezimage
     * can't be "blank", and any field with a minimum-value validator).
     *
     * @return array<string, mixed>
     */
    private function describeField(FieldDefinition $fieldDef): array
    {
        $info = [
            'type' => $fieldDef->fieldTypeIdentifier,
            'required' => $this->isFieldRequired($fieldDef),
        ];

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZMATRIX) {
            $columns = [];
            foreach ((array) ($fieldDef->fieldSettings['columns'] ?? []) as $column) {
                $columns[] = [
                    'identifier' => (string) ($column['identifier'] ?? ''),
                    'name' => (string) ($column['name'] ?? ''),
                ];
            }
            $info['columns'] = $columns;
        }

        if ($fieldDef->fieldTypeIdentifier === FieldType::EZOBJECTRELATIONLIST) {
            $info['allowedTypes'] = array_values(
                (array) ($fieldDef->fieldSettings['selectionContentTypes'] ?? [])
            );
        }

        return $info;
    }

    /**
     * Determine whether a field is required based on its definition and type.
     *
     * Strategy (in priority order):
     *   1. Trust the content type's isRequired flag (set by Ibexa's mapper).
     *   2. ezimage is always required — there is no "empty" image value.
     *   3. Fall back to validator configuration (StringLength min > 0,
     *      minimum row count, minimum relation limit, etc.).
     *
     * @internal Exposed for testing via describeField(); not part of the public API.
     */
    private function isFieldRequired(FieldDefinition $fieldDef): bool
    {
        if ($fieldDef->isRequired()) {
            return true;
        }

        if ($fieldDef->fieldTypeIdentifier === 'ezimage') {
            return true;
        }

        $validator = $fieldDef->getValidatorConfiguration();

        if (isset($validator['StringLengthValidator']['minStringLength'])
            && (int) $validator['StringLengthValidator']['minStringLength'] > 0) {
            return true;
        }

        if (isset($validator['MatrixValueValidator']['minimumRowCount'])
            && (int) $validator['MatrixValueValidator']['minimumRowCount'] > 0) {
            return true;
        }

        if (isset($validator['RelationValidator']['minimumRelationLimit'])
            && (int) $validator['RelationValidator']['minimumRelationLimit'] > 0) {
            return true;
        }

        return false;
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

        $types = $this->contentTypeService->loadContentTypes($group);
        if (!is_iterable($types)) {
            return [];
        }

        return is_array($types) ? array_values($types) : iterator_to_array($types, false);
    }
}
