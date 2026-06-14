<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\Field\BlockFlattener;
use Masilia\AiAssistant\Field\SiblingFieldsExtractor;
use Masilia\AiAssistant\SystemPromptContext;
use Psr\Log\LoggerInterface;

readonly class AgentOrchestrator
{
    public function __construct(
        private IntentClassifier $classifier,
        private BlockCatalog     $blockCatalog,
        private ToolRegistry     $toolRegistry,
        private ConfigResolverInterface $configResolver,
        private BlockFlattener $blockFlattener,
        private SiblingFieldsExtractor $siblingFieldsExtractor,
        private AiClientInterface $aiClient,
        private LlmPromptBuilder $promptBuilder,
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * Process a user message and return an agent response.
     */
    public function chat(string $message): AgentResponse
    {
        // 1. Classify intent via LLM
        $classification = $this->classifier->classify($message);

        if ($classification === null) {
            return AgentResponse::error('I could not understand your request. Please try again.');
        }

        $intent = $classification['intent'];
        $params = $classification['parameters'];

        // 2. Route to appropriate handler
        return match ($intent) {
            'create_page' => $this->handleCreatePage($params),
            'create_content' => $this->handleCreateContent($params),
            'update_content' => $this->handleUpdateContent($params),
            'delete_content' => $this->executeTool('trash_content', $params),
            'search_content' => $this->executeTool('search_content', $params),
            'generate_image' => $this->executeTool('generate_image', $params),
            'list_blocks' => $this->handleListBlocks(),
            'undo' => $this->handleUndo($params),
            'set_site' => $this->handleSetSite($params),
            'browse_site_structure' => $this->executeTool('browse_site_structure', $params),
            'create_site_structure' => $this->executeTool('create_site_structure', $params),
            default => AgentResponse::error(sprintf('Unknown intent: %s', $intent)),
        };
    }

    /**
     * Execute a pre-built plan.
     */
    public function executePlan(AgentPlan $plan): AgentResponse
    {
        $results = [];
        foreach ($plan->steps as $step) {
            $tool = $this->toolRegistry->get($step['tool']);
            if ($tool === null) {
                $results[] = ToolResult::error(sprintf('Tool not found: %s', $step['tool']));
                continue;
            }

            $result = $tool->execute($step['params']);
            $results[] = $result;

            // Stop on error
            if (!$result->success) {
                break;
            }
        }

        return AgentResponse::withResults($results);
    }

    private function handleCreatePage(array $params): AgentResponse
    {
        $siteaccess = $params['siteaccess'] ?? '';
        $parentLocationId = $this->resolveParentLocation($siteaccess, $params);

        if ($parentLocationId === null) {
            return AgentResponse::error(
                $siteaccess !== ''
                    ? sprintf('Could not resolve root location for siteaccess "%s".', $siteaccess)
                    : 'Please specify a siteaccess (e.g. "for mattcch site").',
            );
        }

        $blocksFromLlm = $params['blocks'] ?? [];

        $plan = new AgentPlan(
            steps: [
                [
                    'tool' => 'create_page_structure',
                    'params' => [
                        'title' => $params['title'] ?? 'Untitled Page',
                        'description' => $params['description'] ?? '',
                        'parent_location_id' => $parentLocationId,
                        'blocks' => $blocksFromLlm,
                    ],
                    'description' => sprintf(
                        'Create page "%s" with %d blocks',
                        $params['title'] ?? 'Untitled Page',
                        count($blocksFromLlm),
                    ),
                ],
            ],
            description: sprintf(
                'Create page "%s" with %d blocks under "%s"',
                $params['title'] ?? 'Untitled Page',
                count($blocksFromLlm),
                $siteaccess ?: 'root',
            ),
            requiresApproval: true,
        );

        return AgentResponse::withPlan($plan, sprintf(
            'I will create a page "%s" under the "%s" site with the following structure:',
            $params['title'] ?? 'Untitled Page',
            $siteaccess ?: 'default',
        ));
    }

    private function handleCreateContent(array $params): AgentResponse
    {
        // Resolve siteaccess to root location if provided
        $siteaccess = $params['siteaccess'] ?? '';
        if ($siteaccess !== '' && !isset($params['parent_location_id'])) {
            $rootLocationId = $this->resolveParentLocation($siteaccess, $params);
            if ($rootLocationId !== null) {
                $params['parent_location_id'] = $rootLocationId;
            }
        }

        return $this->executeTool('create_content', $params);
    }

    private function handleUpdateContent(array $params): AgentResponse
    {
        $attributes = $params['attributes'] ?? [];

        // Check if this is a novaseometas update — needs enriched context
        $isSeoUpdate = isset($attributes['novaseometas']);
        if ($isSeoUpdate) {
            return $this->handleUpdateSeo($params);
        }

        // If content_id is provided directly, execute update directly
        if (isset($params['content_id'])) {
            return $this->executeTool('update_content', $params);
        }

        // If siteaccess + page_name are provided, search first then update
        $siteaccess = $params['siteaccess'] ?? '';
        $pageName = $params['page_name'] ?? '';

        if ($siteaccess === '' || $pageName === '') {
            return AgentResponse::error(
                'Please provide either a content_id, or both siteaccess and page_name to search for the page.',
            );
        }

        // 1. Resolve siteaccess to root location
        $rootLocationId = $this->resolveParentLocation($siteaccess, []);
        if ($rootLocationId === null) {
            return AgentResponse::error(
                sprintf('Could not resolve root location for siteaccess "%s".', $siteaccess),
            );
        }

        // 2. Search for the page by name within the siteaccess subtree
        $searchResult = $this->executeTool('search_content', [
            'content_type' => 'page',
            'name' => $pageName,
            'subtree_location_id' => $rootLocationId,
            'limit' => 1,
        ]);

        if (!$searchResult->success) {
            return $searchResult;
        }

        $searchData = $searchResult->data ?? [];
        $results = $searchData['results'] ?? [];

        if (empty($results)) {
            return AgentResponse::error(
                sprintf('Page "%s" not found in siteaccess "%s".', $pageName, $siteaccess),
            );
        }

        // 3. Update the found content
        $contentId = $results[0]['content_id'];
        $updateParams = array_merge($params, ['content_id' => $contentId]);

        return $this->executeTool('update_content', $updateParams);
    }

    /**
     * Handle novaseometas updates with enriched context from block flattening.
     */
    private function handleUpdateSeo(array $params): AgentResponse
    {
        $contentId = $params['content_id'] ?? null;

        // If no content_id, search for it
        if ($contentId === null) {
            $siteaccess = $params['siteaccess'] ?? '';
            $pageName = $params['page_name'] ?? '';

            if ($siteaccess === '' || $pageName === '') {
                return AgentResponse::error(
                    'Please provide either a content_id, or both siteaccess and page_name to search for the page.',
                );
            }

            $rootLocationId = $this->resolveParentLocation($siteaccess, []);
            if ($rootLocationId === null) {
                return AgentResponse::error(
                    sprintf('Could not resolve root location for siteaccess "%s".', $siteaccess),
                );
            }

            $searchResult = $this->executeTool('search_content', [
                'content_type' => 'page',
                'name' => $pageName,
                'subtree_location_id' => $rootLocationId,
                'limit' => 1,
            ]);

            if (!$searchResult->success) {
                return $searchResult;
            }

            $searchData = $searchResult->data ?? [];
            $results = $searchData['results'] ?? [];

            if (empty($results)) {
                return AgentResponse::error(
                    sprintf('Page "%s" not found in siteaccess "%s".', $pageName, $siteaccess),
                );
            }

            $contentId = $results[0]['content_id'];
        }

        // Load the content
        $contentService = $this->repository->getContentService();
        try {
            $content = $contentService->loadContent($contentId);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(
                '[Agent] Could not load content {id} for SEO update: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return AgentResponse::error(sprintf('Could not load content with ID %d.', $contentId));
        }

        // Flatten blocks + non-block fields for context
        $languageCode = $content->contentInfo->mainLanguageCode;
        $blockText = $this->blockFlattener->flatten($content, $languageCode);

        // Extract sibling fields for context
        $contentType = $this->repository->getContentTypeService()->loadContentType(
            $content->contentInfo->contentTypeId,
        );
        $siblingFields = $this->siblingFieldsExtractor->extract(
            $content,
            $contentType,
            'novaseometas',
            $languageCode,
        );

        // Build enriched system prompt
        $systemPrompt = $this->promptBuilder->buildSeoSystemPrompt(
            $contentType->getName(),
            $content->contentInfo->name ?? '',
            $blockText,
            $siblingFields,
            $params['attributes']['novaseometas']['metaKeys'] ?? [],
        );

        // Build user prompt
        $userPrompt = 'Generate SEO metadata for this page based on the content provided.';

        // Call LLM with enriched context
        try {
            $seoResponse = $this->aiClient->suggest($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(
                '[Agent] SEO generation failed for content {id}: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return AgentResponse::error('Failed to generate SEO metadata. Please try again.');
        }

        // Parse the LLM response
        $seoData = json_decode($seoResponse, true);
        if (!is_array($seoData)) {
            $this->aiLogger->warning(
                '[Agent] Invalid SEO response for content {id}: {response}',
                ['id' => $contentId, 'response' => $seoResponse],
            );

            return AgentResponse::error('Failed to parse SEO metadata. Please try again.');
        }

        // Clear cache after potential update
        $this->blockFlattener->clearCache($contentId);

        // Execute the update with the generated SEO values
        $updateParams = array_merge($params, [
            'content_id' => $contentId,
            'attributes' => ['novaseometas' => $seoData],
        ]);

        return $this->executeTool('update_content', $updateParams);
    }

    private function handleListBlocks(): AgentResponse
    {
        $blocks = $this->blockCatalog->getAvailableBlocks();
        $capabilities = $this->blockCatalog->getCapabilities();

        $message = "Available block types:\n\n";
        foreach ($capabilities as $cap => $types) {
            $message .= sprintf("**%s:**\n", ucfirst($cap));
            foreach ($types as $type) {
                $info = $blocks[$type] ?? null;
                $fields = $info ? implode(', ', array_keys($info['fields'])) : '';
                $message .= sprintf("  - %s (%s)\n", $type, $fields);
            }
            $message .= "\n";
        }

        return new AgentResponse(message: $message);
    }

    private function handleUndo(array $params): AgentResponse
    {
        $tool = $this->toolRegistry->get('undo_last');
        if ($tool === null) {
            return AgentResponse::error('Undo tool not available.');
        }

        $result = $tool->execute($params);

        return AgentResponse::withResults([$result], $result->message);
    }

    private function handleSetSite(array $params): AgentResponse
    {
        $siteaccess = $params['siteaccess'] ?? '';
        if ($siteaccess === '') {
            return AgentResponse::error('Please specify a siteaccess name.');
        }

        return AgentResponse::withResults(
            [],
            sprintf('Siteaccess set to "%s". Future operations will target this site tree.', $siteaccess),
        );
    }

    private function executeTool(string $toolName, array $params): AgentResponse
    {
        $tool = $this->toolRegistry->get($toolName);
        if ($tool === null) {
            return AgentResponse::error(sprintf('Tool "%s" not available.', $toolName));
        }

        $result = $tool->execute($params);

        return AgentResponse::withResults([$result], $result->message);
    }

    /**
     * Resolve parent location ID from siteaccess name or explicit parameter.
     *
     * Priority:
     * 1. Explicit parent_location_id in params (user specified a subfolder)
     * 2. Resolve from siteaccess name via ConfigResolver
     * 3. Current request siteaccess fallback
     */
    private function resolveParentLocation(string $siteaccess, array $params): ?int
    {
        // 1. Explicit parent_location_id
        if (isset($params['parent_location_id'])) {
            return (int) $params['parent_location_id'];
        }

        // 2. Resolve from siteaccess name
        if ($siteaccess !== '') {
            try {
                return (int) $this->configResolver->getParameter(
                    'content.tree_root.location_id',
                    null,
                    $siteaccess,
                );
            } catch (\Throwable) {
                return null;
            }
        }

        // 3. Current request siteaccess
        try {
            return (int) $this->configResolver->getParameter('content.tree_root.location_id');
        } catch (\Throwable) {
            return null;
        }
    }
}
