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
        return 'Search for content using criteria like content type, full text, and subtree.';
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
            $result = $this->repository->sudo(function () use ($params) {
                $searchService = $this->repository->getSearchService();
                $locationService = $this->repository->getLocationService();

                $criteria = [];

                if (isset($params['content_type'])) {
                    $criteria[] = new Criterion\ContentTypeIdentifier([$params['content_type']]);
                }

                if (isset($params['query'])) {
                    $criteria[] = new Criterion\FullText($params['query']);
                }

                if (isset($params['subtree_location_id'])) {
                    $location = $locationService->loadLocation((int) $params['subtree_location_id']);
                    $criteria[] = new Criterion\Subtree($location->pathString);
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

                return [
                    'count' => $searchResult->totalCount,
                    'results' => $results,
                ];
            });

            return ToolResult::ok(
                sprintf('Found %d results', $result['count']),
                $result,
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to search content: %s', $e->getMessage()));
        }
    }
}
