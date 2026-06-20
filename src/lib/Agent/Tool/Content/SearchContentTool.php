<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Core\Repository\Values\Content\Content;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\AiConstants;
use Psr\Log\LoggerInterface;

readonly class SearchContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::SEARCH_CONTENT;
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
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
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
                } catch (NotFoundException) {
                    return ToolResult::error(
                        sprintf('Location %d not found', (int) $params['subtree_location_id']),
                    );
                } catch (UnauthorizedException $e) {
                    return ToolResult::error(
                        sprintf('Access denied to location %d', (int) $params['subtree_location_id']),
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
                'query' => !empty($criteria) ? new LogicalAnd($criteria) : null,
                'limit' => $params['limit'] ?? 10,
            ]);

            $searchResult = $searchService->findContent($query);


            $results = [];
            foreach ($searchResult->searchHits as $hit) {
                /** @var   Content  $content */
                $content = $hit->valueObject;

                $results[] = [
                    'content_id' => $content->id,
                    'content_type' => $content->getContentType()->identifier,
                    'name' => $content->contentInfo->name,
                    'remote_id' => $content->contentInfo->remoteId,
                ];
            }

            return ToolResult::ok(
                sprintf('Found %d results', $searchResult->totalCount),
                [
                    'count' => $searchResult->totalCount,
                    'results' => $results
                ]
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'search content');
        }
    }
}
