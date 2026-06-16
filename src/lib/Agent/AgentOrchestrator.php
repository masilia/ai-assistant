<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Block\BlockDesigner;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\AiConstants;
use Masilia\AiAssistant\Field\FieldType;

readonly class AgentOrchestrator
{
    public function __construct(
        private Repository $repository,
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
            IntentName::CREATE_PAGE => $this->handleCreatePage($params),
            IntentName::CREATE_CONTENT => $this->handleCreateContent($params),
            IntentName::UPDATE_CONTENT => $this->handleUpdateContent($params),
            IntentName::DELETE_CONTENT => $this->executeTool(ToolName::TRASH_CONTENT, $params),
            IntentName::SEARCH_CONTENT => $this->executeTool(ToolName::SEARCH_CONTENT, $params),
            IntentName::GENERATE_IMAGE => $this->executeTool(ToolName::GENERATE_IMAGE, $params),
            IntentName::LIST_BLOCKS => $this->handleListBlocks(),
            IntentName::UNDO => $this->handleUndo($params),
            IntentName::BROWSE_SITE_STRUCTURE => $this->executeTool(ToolName::BROWSE_SITE_STRUCTURE, $params),
            IntentName::CREATE_SITE_STRUCTURE => $this->executeTool(ToolName::CREATE_SITE_STRUCTURE, $params),
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
        $isSeoUpdate = isset($attributes[FieldType::NOVASEOMETAS]);

        try {
            $contentId = $params['content_id'] ?? null;
            if ($contentId === null) {
                $contentId = $this->resolveContentByName($params);
            }
        } catch (ContentNotFoundException $e) {
            return AgentResponse::error($e->getMessage());
        }

        if ($isSeoUpdate) {
            return $this->handleSeoUpdate((int) $contentId, $params);
        }

        return $this->executeTool(ToolName::UPDATE_CONTENT, array_merge($params, ['content_id' => $contentId]));
    }

    /**
     * Generate SEO metadata and return a plan for user confirmation.
     */
    private function handleSeoUpdate(int $contentId, array $params): AgentResponse
    {
        $seoData = $this->seoHandler->generateMetadata($contentId, $params);

        if ($seoData === null) {
            return AgentResponse::error('Failed to generate SEO metadata. Please try again.');
        }

        // Resolve actual field identifier from content type — novaseometas is
        // the field *type*, not the field *identifier* (e.g. "seo_metadata")
        $content = $this->repository->getContentService()->loadContent($contentId);
        $fieldDef = $content->getContentType()->getFirstFieldDefinitionOfType(FieldType::NOVASEOMETAS);

        if ($fieldDef === null) {
            return AgentResponse::error('This content type does not have a novaseometas field.');
        }

        $resolvedAttributes = [$fieldDef->identifier => $seoData];
        $pageName = $params['page_name'] ?? sprintf('content %d', $contentId);

        $plan = new AgentPlan(
            steps: [
                [
                    'tool' => ToolName::UPDATE_CONTENT,
                    'params' => array_merge($params, [
                        'content_id' => $contentId,
                        'attributes' => $resolvedAttributes,
                    ]),
                    'description' => sprintf('Apply SEO metadata to "%s"', $pageName),
                ],
            ],
            description: sprintf('Apply generated SEO metadata to "%s"', $pageName),
            requiresApproval: true,
        );

        $preview = $this->formatSeoPreview($seoData);

        return AgentResponse::withPlan($plan, sprintf(
            "Here are the SEO metadata I generated for \"%s\":\n\n%s\n\nShall I apply these to the page?",
            $pageName,
            $preview,
        ));
    }

    /**
     * Format SEO data as a readable preview for the plan message.
     */
    private function formatSeoPreview(array $seoData): string
    {
        $lines = [];
        foreach ($seoData as $key => $value) {
            if (is_string($value)) {
                $lines[] = sprintf('**%s**: %s', $key, $value);
            } elseif (is_array($value)) {
                $lines[] = sprintf('**%s**: %s', $key, json_encode($value));
            }
        }

        return implode("\n", $lines) ?: '(empty)';
    }

    /**
     * Resolve content_id from siteaccess + page_name params.
     *
     * @throws ContentNotFoundException
     */
    private function resolveContentByName(array $params): int
    {
        $siteaccess = $params['siteaccess'] ?? '';
        $pageName = $params['page_name'] ?? '';
        $contentType = $params['content_type'] ?? 'page';

        if ($siteaccess === '' || $pageName === '') {
            throw new ContentNotFoundException(
                'Please provide either a content_id, or both siteaccess and page_name to search for the page.',
            );
        }

        $contentId = $this->contentResolver->findBySiteaccessAndName($siteaccess, $pageName, $contentType);
        if ($contentId === null) {
            throw new ContentNotFoundException(
                sprintf('Page "%s" not found in siteaccess "%s" (content type: "%s").', $pageName, $siteaccess, $contentType),
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
        $message = $this->blockCatalog->renderBlockSummary();

        return new AgentResponse(message: $message ?: 'No block types available.');
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
