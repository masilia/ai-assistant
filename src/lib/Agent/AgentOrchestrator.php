<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Block\BlockDesigner;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\AiConstants;

readonly class AgentOrchestrator
{
    public function __construct(
        private IntentClassifier $classifier,
        private BlockCatalog     $blockCatalog,
        private BlockDesigner    $blockDesigner,
        private ToolRegistry     $toolRegistry,
        private SiteaccessLocationResolver $locationResolver,
        private ContentResolver $contentResolver,
        private SeoMetadataHandler $seoHandler,
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
            'delete_content' => $this->executeTool(ToolName::TRASH_CONTENT, $params),
            'search_content' => $this->executeTool(ToolName::SEARCH_CONTENT, $params),
            'generate_image' => $this->executeTool(ToolName::GENERATE_IMAGE, $params),
            'list_blocks' => $this->handleListBlocks(),
            'undo' => $this->handleUndo($params),
            'browse_site_structure' => $this->executeTool(ToolName::BROWSE_SITE_STRUCTURE, $params),
            'create_site_structure' => $this->executeTool(ToolName::CREATE_SITE_STRUCTURE, $params),
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

        // Validate + order blocks via the designer (drops invalid blocks,
        // sorts by capability: hero → content → cta).
        $design = $this->blockDesigner->designPageStructure($params);
        $designArray = $design->toArray();
        $blocks = $designArray['blocks'];
        $title = $design->title !== '' ? $design->title : 'Untitled Page';

        $plan = new AgentPlan(
            steps: [
                [
                    'tool' => ToolName::CREATE_PAGE_STRUCTURE,
                    'params' => [
                        'title' => $title,
                        'description' => $design->description,
                        'parent_location_id' => $parentLocationId,
                        'blocks' => $blocks,
                    ],
                    'description' => sprintf('Create page "%s" with %d blocks', $title, count($blocks)),
                ],
            ],
            description: sprintf(
                'Create page "%s" with %d blocks under "%s"',
                $title,
                count($blocks),
                $siteaccess ?: 'root',
            ),
            requiresApproval: true,
        );

        return AgentResponse::withPlan($plan, sprintf(
            'I will create a page "%s" under the "%s" site with the following structure:',
            $title,
            $siteaccess ?: AiConstants::DEFAULT_SITEACCESS,
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

        return $this->executeTool(ToolName::CREATE_CONTENT, $params);
    }

    private function handleUpdateContent(array $params): AgentResponse
    {
        $attributes = $params['attributes'] ?? [];
        $isSeoUpdate = isset($attributes['novaseometas']);

        $contentId = $params['content_id'] ?? null;
        if ($contentId === null) {
            $contentId = $this->resolveContentByName($params);
            if ($contentId instanceof AgentResponse) {
                return $contentId;
            }
        }

        if ($isSeoUpdate) {
            return $this->seoHandler->generateAndApply((int) $contentId, $params);
        }

        return $this->executeTool(ToolName::UPDATE_CONTENT, array_merge($params, ['content_id' => $contentId]));
    }

    /**
     * Resolve content_id from siteaccess + page_name params.
     * Returns int on success, AgentResponse(error) on failure.
     */
    private function resolveContentByName(array $params): int|AgentResponse
    {
        $siteaccess = $params['siteaccess'] ?? '';
        $pageName = $params['page_name'] ?? '';

        if ($siteaccess === '' || $pageName === '') {
            return AgentResponse::error(
                'Please provide either a content_id, or both siteaccess and page_name to search for the page.',
            );
        }

        $contentId = $this->contentResolver->findBySiteaccessAndName($siteaccess, $pageName);
        if ($contentId === null) {
            return AgentResponse::error(
                sprintf('Page "%s" not found in siteaccess "%s".', $pageName, $siteaccess),
            );
        }

        return $contentId;
    }

    /**
     * Special-case route: returns markdown directly in the message field
     * instead of structured ToolResult data. Frontend renders as-is.
     */
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
        $tool = $this->toolRegistry->get(ToolName::UNDO_LAST_OPERATION);
        if ($tool === null) {
            return AgentResponse::error('Undo tool not available.');
        }

        $result = $tool->execute($params);

        return AgentResponse::withResults([$result], $result->message);
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

    private function resolveParentLocation(string $siteaccess, array $params): ?int
    {
        $explicitId = isset($params['parent_location_id']) ? (int) $params['parent_location_id'] : null;

        return $this->locationResolver->resolve($siteaccess, $explicitId);
    }
}
