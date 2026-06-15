<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Core\Repository\Values\Content\Content;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class BrowseSiteStructureTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private SiteaccessLocationResolver $locationResolver,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::BROWSE_SITE_STRUCTURE;
    }

    public function getDescription(): string
    {
        return 'Browse the site content tree. Shows pages and content under a siteaccess root.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Siteaccess name (default: current)',
                ],
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location to browse (overrides siteaccess)',
                ],
                'depth' => [
                    'type' => 'integer',
                    'description' => 'Max tree depth (default: 2)',
                    'default' => 2,
                ],
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Filter by content type identifier',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max items to load (default: 500)',
                    'default' => 500,
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $searchService = $this->repository->getSearchService();
            $locationService = $this->repository->getLocationService();

            // 1. Resolve root location
            $explicitId = isset($params['location_id']) ? (int) $params['location_id'] : null;
            $rootLocationId = $this->locationResolver->resolve($params['siteaccess'] ?? '', $explicitId);
            if ($rootLocationId === null) {
                return ToolResult::error('Could not resolve root location. Provide a siteaccess name or location_id.');
            }

            $rootLocation = $locationService->loadLocation($rootLocationId);
            $rootPath = $rootLocation->getPathString();

            // 2. Search all content under root subtree
            $criteria = [
                new Criterion\Subtree($rootPath),
            ];

            if (!empty($params['content_type'])) {
                $criteria[] = new Criterion\ContentTypeIdentifier([$params['content_type']]);
            }

            $query = new Query([
                'filter' => new LogicalAnd($criteria),
                'limit' => $params['limit'] ?? 500,
            ]);

            $searchResult = $searchService->findContent($query);

            // 3. Collect items with location info
            $items = [];
            foreach ($searchResult->searchHits as $hit) {
                /**@var Content $content*/
                $content = $hit->valueObject;
                $location = $content->getContentInfo()->getMainLocation();

                $items[] = [
                    'location_id' => $location->id,
                    'parent_location_id' => $location->parentLocationId,
                    'content_id' => $content->id,
                    'content_type' => $content->getContentType()->identifier,
                    'name' => $content->getName(),
                ];
            }

            // 4. Build tree from parentLocationId relationships
            $depth = $params['depth'] ?? 2;
            $tree = $this->buildTree($items, $rootLocationId, $depth);

            // Find siteaccess name for response
            $siteaccess = $params['siteaccess'] ?? null;

            return ToolResult::ok(
                sprintf('Loaded site structure (%d items)', count($items)),
                [
                    'siteaccess' => $siteaccess,
                    'root_location_id' => $rootLocationId,
                    'total_items' => count($items),
                    'children' => $tree,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'browse site structure');
        }
    }

    private function buildTree(array $items, int $parentId, int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $children = [];
        foreach ($items as $item) {
            if ($item['parent_location_id'] === $parentId) {
                $node = [
                    'location_id' => $item['location_id'],
                    'content_id' => $item['content_id'],
                    'content_type' => $item['content_type'],
                    'name' => $item['name'],
                ];

                $subChildren = $this->buildTree($items, $item['location_id'], $depth - 1);
                if (!empty($subChildren)) {
                    $node['children'] = $subChildren;
                }

                $children[] = $node;
            }
        }

        return $children;
    }
}
