<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Flattens a page content's blocks + non-block fields into a readable
 * text summary suitable for LLM prompt context.
 *
 * Uses batch loading for blocks and PSR-6 caching for performance.
 */
class BlockFlattener
{
    private const CACHE_PREFIX = 'ai_block_flattener_';
    private const CACHE_TTL = 3600; // 1 hour
    private const BLOCKS_FIELD = 'blocks';
    private const MAX_SIBLING_CHARS = 500;

    private ContentService $contentService;
    private ContentTypeService $contentTypeService;
    private LanguageService $languageService;
    private LocationService $locationService;
    private FieldValueStringifierRegistry $stringifierRegistry;
    private CacheItemPoolInterface $cachePool;
    private LoggerInterface $aiLogger;

    public function __construct(
        Repository $repository,
        FieldValueStringifierRegistry $stringifierRegistry,
        CacheItemPoolInterface $cachePool,
        LoggerInterface $aiLogger,
    ) {
        $this->contentService = $repository->getContentService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->languageService = $repository->getContentLanguageService();
        $this->locationService = $repository->getLocationService();
        $this->stringifierRegistry = $stringifierRegistry;
        $this->cachePool = $cachePool;
        $this->aiLogger = $aiLogger;
    }

    /**
     * Flatten a content's blocks + non-block fields into readable text.
     *
     * @param string $languageCode Language code (e.g. 'eng-GB')
     * @param string $blocksFieldIdentifier The field identifier for blocks (default: 'blocks')
     */
    public function flatten(
        Content $content,
        string $languageCode = 'eng-GB',
        string $blocksFieldIdentifier = self::BLOCKS_FIELD,
    ): string {
        $cacheKey = self::CACHE_PREFIX . $content->id . '-' . $languageCode;
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $output = '';

        // 1. Include content metadata
        $output .= $this->buildMetadata($content, $languageCode);

        // 2. Include non-block fields
        $output .= $this->flattenNonBlockFields($content, $languageCode, $blocksFieldIdentifier);

        // 3. Flatten blocks field
        $output .= $this->flattenBlocks($content, $languageCode, $blocksFieldIdentifier);

        // Cache the result
        $cacheItem->set($output);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cachePool->save($cacheItem);

        return $output;
    }

    /**
     * Build content metadata (type, title, path).
     */
    private function buildMetadata(Content $content, string $languageCode): string
    {
        $contentType = $content->getContentType();

        $output = sprintf("Content type: %s\n", $contentType->getName());
        $output .= sprintf("Content title: %s\n", $content->getName($languageCode));

        // Add site path if location exists
        $path = $this->getSitePath($content);
        if ($path !== '') {
            $output .= sprintf("Location path: %s\n", $path);
        }

        return $output . "\n";
    }

    /**
     * Get the site hierarchy path for a content item.
     */
    private function getSitePath(Content $content): string
    {
        $location = $content->contentInfo->getMainLocation();
        if ($location === null) {
            return '';
        }

        $pathIds = $location->path;

        if (count($pathIds) <= 1) {
            return '/';
        }

        // Load all locations in the path
        $locations = $this->locationService->loadLocationList($pathIds);

        // Batch-load content info for name resolution
        $contentIds = [];
        foreach ($locations as $loc) {
            $contentIds[] = $loc->contentId;
        }
        $contentInfoList = $this->contentService->loadContentInfoList(array_unique($contentIds));

        $pathParts = [];
        foreach ($pathIds as $pathId) {
            $loc = $locations[$pathId] ?? null;
            if ($loc === null) {
                continue;
            }
            $info = $contentInfoList[$loc->contentId] ?? null;
            $pathParts[] = $info?->getName() ?? (string)$pathId;
        }

        return '/' . implode('/', $pathParts);
    }

    /**
     * Flatten non-block fields (skips blocks and novaseometas).
     */
    private function flattenNonBlockFields(
        Content $content,
        string $languageCode,
        string $blocksFieldIdentifier,
    ): string {
        $contentType = $content->getContentType();

        $output = '';
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            // Skip blocks field (handled separately) and novaseometas (generating it)
            if ($fieldDef->identifier === $blocksFieldIdentifier
                || $fieldDef->fieldTypeIdentifier === FieldType::NOVASEOMETAS) {
                continue;
            }

            $field = $this->getFieldWithFallback($content, $fieldDef->identifier, $languageCode);
            if ($field === null) {
                continue;
            }

            $stringValue = $this->stringifierRegistry->toString($field, $fieldDef);
            if ($stringValue === '') {
                continue;
            }

            // Truncate long values
            if (mb_strlen($stringValue) > self::MAX_SIBLING_CHARS) {
                $stringValue = mb_substr($stringValue, 0, self::MAX_SIBLING_CHARS) . '...';
            }

            $label = $fieldDef->getName() ?: $fieldDef->identifier;
            $output .= sprintf("%s: %s\n", $label, $this->scrubForPrompt($stringValue));
        }

        return $output !== '' ? $output . "\n" : '';
    }

    /**
     * Flatten blocks field into readable text.
     * Uses batch loading for all blocks in a single request.
     */
    private function flattenBlocks(
        Content $content,
        string $languageCode,
        string $blocksFieldIdentifier,
    ): string {
        $blocksField = $content->getField($blocksFieldIdentifier);
        if ($blocksField === null || $blocksField->value === null) {
            return '';
        }

        $blockIds = $blocksField->value->destinationContentIds ?? [];
        if ($blockIds === []) {
            return '';
        }

        // Batch-load all blocks (single request)
        $contentInfoList = $this->contentService->loadContentInfoList($blockIds);
        if (empty($contentInfoList)) {
            return '';
        }

        $blockContents = $this->contentService->loadContentListByContentInfo(
            $contentInfoList,
            [$languageCode],
        );

        if (empty($blockContents)) {
            return '';
        }

        // Load content types for names (batch)
        $contentTypeIds = [];
        foreach ($blockContents as $block) {
            $contentTypeIds[] = $block->contentInfo->contentTypeId;
        }
        $uniqueTypeIds = array_unique($contentTypeIds);
        $contentTypes = [];
        foreach ($this->contentTypeService->loadContentTypeList($uniqueTypeIds) as $type) {
            $contentTypes[$type->id] = $type;
        }

        // Flatten each block
        $output = "Page blocks:\n";
        $index = 1;
        foreach ($blockContents as $block) {
            $blockType = $contentTypes[$block->contentInfo->contentTypeId]->getName() ?? 'Block';

            $output .= sprintf("\n- %s (Block %d):\n", $blockType, $index);

            foreach ($block->fields as $field) {
                // Skip novaseometas in blocks too
                if ($field->fieldDefinition->fieldTypeIdentifier === FieldType::NOVASEOMETAS) {
                    continue;
                }

                $stringValue = $this->stringifierRegistry->toString(
                    $field,
                    $field->fieldDefinition,
                );

                if ($stringValue === '') {
                    continue;
                }

                // Truncate long values
                if (mb_strlen($stringValue) > self::MAX_SIBLING_CHARS) {
                    $stringValue = mb_substr($stringValue, 0, self::MAX_SIBLING_CHARS) . '...';
                }

                $label = $field->fieldDefinition->getName() ?? $field->identifier;
                $output .= sprintf("  %s: %s\n", $label, $this->scrubForPrompt($stringValue));
            }

            $index++;
        }

        return $output;
    }

    /**
     * Get a field value with language fallback.
     */
    private function getFieldWithFallback(
        Content $content,
        string $fieldIdentifier,
        string $languageCode,
    ): ?\Ibexa\Contracts\Core\Repository\Values\Content\Field {
        // Try the requested language first
        $field = $content->getField($fieldIdentifier, $languageCode);
        if ($field !== null && $field->value !== null) {
            return $field;
        }

        // Try the main language
        $mainLanguage = $content->contentInfo->mainLanguageCode;
        if ($mainLanguage !== $languageCode) {
            $field = $content->getField($fieldIdentifier, $mainLanguage);
            if ($field !== null && $field->value !== null) {
                return $field;
            }
        }

        // Try always available
        $field = $content->getField($fieldIdentifier);
        if ($field !== null && $field->value !== null) {
            return $field;
        }

        return null;
    }

    /**
     * Scrub a string value for safe inclusion in LLM prompts.
     */
    private function scrubForPrompt(string $value): string
    {
        return str_replace(["\n", "\r"], [' ', ''], $value);
    }

    /**
     * Clear cache for a specific content item or all items.
     */
    public function clearCache(?int $contentId = null): void
    {
        if ($contentId === null) {
            // Clear all cache items with our prefix
            // PSR-6 doesn't support deleteByPrefix, so we track keys
            // For now, just log that cache was cleared
            $this->aiLogger->debug('[BlockFlattener] Full cache clear requested');

            return;
        }

        // Delete specific cache keys for this content using all available languages
        $languages = $this->languageService->loadLanguages();
        foreach ($languages as $language) {
            if (!$language->isEnabled()) {
                continue;
            }
            $cacheKey = self::CACHE_PREFIX . $contentId . '-' . $language->languageCode;
            $this->cachePool->delete($cacheKey);
        }
    }
}
