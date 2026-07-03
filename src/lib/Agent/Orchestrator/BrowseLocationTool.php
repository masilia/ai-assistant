<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;

/**
 * Search for content under a specific location (deeper than explore_site).
 *
 * explore_site returns the top-level tree at depth 2 — pages and folders,
 * but NOT the blocks inside each page. This tool lets the LLM search for
 * blocks or items inside a page by name, full-text query, or content type,
 * using the SearchContentTool with a subtree filter. Returns content IDs
 * needed for update_content or trash_content on individual blocks.
 *
 * When include_fields is true, each result also carries its field values
 * (loaded via LoadContentTool) so the LLM can inspect current content
 * before proposing updates.
 */
final readonly class BrowseLocationTool implements OrchestratorTool
{
    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {
    }

    public function getName(): string
    {
        return 'browse_location';
    }

    public function getDescription(): string
    {
        return 'Search for content under a specific location (e.g. blocks inside a page). ' .
            'Use this after explore_site when you need to find content IDs of ' .
            'individual blocks or items inside a page. Supports name search, ' .
            'full-text query, and content type filter. Returns content_id, ' .
            'content_type, and name for each result. Set include_fields=true ' .
            'to also return the current field values of each content item ' .
            '(useful before updating ezmatrix or other fields).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to search under (subtree)',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Full-text search query (e.g. "cookie policy", "what are cookies")',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Search by content name (supports * wildcards, e.g. "paragraph*" or "cookie*")',
                ],
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Filter by content type identifier (e.g. "hero_banner", "text_block")',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (default: 50)',
                    'default' => 50,
                ],
                'include_fields' => [
                    'type' => 'boolean',
                    'description' => 'When true, each result includes its current field values (default: false). Set this to inspect existing content before updating.',
                    'default' => false,
                ],
            ],
            'required' => ['location_id'],
        ];
    }

    public function execute(array $arguments, WorkerContext $context): OrchestratorResponse
    {
        $locationId = $arguments['location_id'] ?? null;
        if (!is_int($locationId) || $locationId <= 0) {
            return OrchestratorResponse::error('browse_location requires a valid location_id');
        }

        $tool = $this->toolRegistry->get(ToolName::SEARCH_CONTENT);
        if ($tool === null) {
            return OrchestratorResponse::error('search_content tool not available');
        }

        $params = [
            'subtree_location_id' => $locationId,
            'limit' => $arguments['limit'] ?? 50,
        ];

        if (isset($arguments['query']) && is_string($arguments['query']) && $arguments['query'] !== '') {
            $params['query'] = $arguments['query'];
        }
        if (isset($arguments['name']) && is_string($arguments['name']) && $arguments['name'] !== '') {
            $params['name'] = $arguments['name'];
        }
        if (isset($arguments['content_type']) && is_string($arguments['content_type']) && $arguments['content_type'] !== '') {
            $params['content_type'] = $arguments['content_type'];
        }

        $result = $tool->execute($params);

        if (!$result->success) {
            return OrchestratorResponse::error($result->message);
        }

        $results = $result->data['results'] ?? [];
        $includeFields = ($arguments['include_fields'] ?? false) === true;

        $items = [];
        foreach ($results as $item) {
            $entry = [
                'content_id' => $item['content_id'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'name' => $item['name'] ?? null,
                'remote_id' => $item['remote_id'] ?? null,
            ];

            if ($includeFields && ($item['content_id'] ?? null) !== null) {
                $entry['fields'] = $this->loadContentFields($this->toolRegistry, (int) $item['content_id']);
            }

            $items[] = $entry;
        }

        return OrchestratorResponse::ok(
            sprintf('Found %d items under location %d', count($items), $locationId),
            ['location_id' => $locationId, 'items' => $items],
        );
    }

    /**
     * Load field values for a single content item via LoadContentTool.
     *
     * @return array<string, mixed>
     */
    private function loadContentFields(ToolRegistry $toolRegistry, int $contentId): array
    {
        $loadTool = $toolRegistry->get(ToolName::LOAD_CONTENT);
        if ($loadTool === null) {
            return [];
        }

        $result = $loadTool->execute(['content_id' => $contentId]);
        if (!$result->success) {
            return [];
        }

        return $result->data['fields'] ?? [];
    }
}
