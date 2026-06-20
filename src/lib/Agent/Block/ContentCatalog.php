<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Catalogs standard Ibexa content types (page, article, folder, etc.)
 * from the non-"Blocks" content type groups.
 *
 * Complements BlockCatalog which only covers block layout types.
 * Used by PlanBuilder to validate required fields for any content type
 * the LLM might propose in a plan.
 */
final class ContentCatalog
{
    use ContentTypeSchemaHelper;

    private const GROUPS = [
        'Content',
        'Block items',
        'Media',
        'Taxonomies',
        'Configurations',
    ];

    private const CACHE_KEY = 'masilia_ai.content_catalog.schemas.v1';
    private const CACHE_TTL = 3600;

    /**
     * Request-scoped memoization.
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
     * Get the schema for a single content type by identifier.
     *
     * @return array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null
     */
    public function getContentTypeSchema(string $identifier): ?array
    {
        return $this->getAvailableContentTypes()[$identifier] ?? null;
    }

    /**
     * Get all standard content types with detailed per-field schemas.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}>
     */
    public function getAvailableContentTypes(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            return $this->memo = $item->get();
        }

        $types = [];
        foreach ($this->loadContentTypes() as $type) {
            $types[$type->identifier] = [
                'identifier' => $type->identifier,
                'name' => $type->getName(),
                'fields' => $this->buildFieldSchemas($type),
            ];
        }

        $item->set($types)->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->memo = $types;
    }

    /**
     * @return ContentType[]
     */
    private function loadContentTypes(): array
    {
        $allTypes = [];

        foreach (self::GROUPS as $groupIdentifier) {
            try {
                $group = $this->contentTypeService->loadContentTypeGroupByIdentifier($groupIdentifier);
            } catch (NotFoundException) {
                continue;
            }

            $types = $this->contentTypeService->loadContentTypes($group);
            if (!is_iterable($types)) {
                continue;
            }

            foreach ($types as $type) {
                $allTypes[] = $type;
            }
        }

        return $allTypes;
    }
}
