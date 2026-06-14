<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class SearchContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'search_content';
    }

    public function getDescription(): string
    {
        return 'Search for content using criteria like content type, name, full text, and subtree.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier to filter by',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Search by content name (supports * wildcards, e.g. "homepage" or "about*")',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Full text search query',
                ],
                'subtree_location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to search under (subtree)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (default: 10)',
                    'default' => 10,
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

            $criteria = [];

            // AND filters
            if (!empty($params['content_type'])) {
                $criteria[] = new Criterion\ContentTypeIdentifier([$params['content_type']]);
            }

            if (!empty($params['subtree_location_id'])) {
                try {
                    $location = $locationService->loadLocation((int) $params['subtree_location_id']);
                    $criteria[] = new Criterion\Subtree($location->pathString);
                } catch (\Throwable) {
                    return ToolResult::error(
                        sprintf('Location %d not found', (int) $params['subtree_location_id']),
                    );
                }
            }

            // OR filter: name OR fulltext
            $orCriteria = [];
            if (!empty($params['name'])) {
                $orCriteria[] = new Criterion\ContentName($params['name']);
            }
            if (!empty($params['query'])) {
                $orCriteria[] = new Criterion\FullText($params['query']);
            }
            if (!empty($orCriteria)) {
                $criteria[] = new Criterion\LogicalOr($orCriteria);
            }

            $query = new Query([
                'filter' => !empty($criteria) ? new LogicalAnd($criteria) : null,
                'limit' => $params['limit'] ?? 10,
            ]);

            $searchResult = $searchService->findContent($query);

            $results = [];
            foreach ($searchResult->searchHits as $hit) {
                $content = $hit->valueObject;
                $results[] = [
                    'content_id' => $content->id,
                    'content_type' => $content->contentInfo->contentTypeId,
                    'name' => $content->contentInfo->name,
                    'remote_id' => $content->remoteId,
                ];
            }

            return ToolResult::ok(
                sprintf('Found %d results', $searchResult->totalCount),
                ['count' => $searchResult->totalCount, 'results' => $results],
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to search content: %s', $e->getMessage()));
        }
    }
}
