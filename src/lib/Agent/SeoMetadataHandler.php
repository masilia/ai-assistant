<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\Field\BlockFlattener;
use Masilia\AiAssistant\Field\SiblingFieldsExtractor;
use Psr\Log\LoggerInterface;

/**
 * Generates SEO metadata via the LLM and applies it to a content item.
 *
 * Flow: load content → flatten blocks → extract sibling fields →
 * build SEO prompt → call LLM → parse JSON → invoke update_content tool.
 */
readonly class SeoMetadataHandler
{
    public function __construct(
        private Repository $repository,
        private BlockFlattener $blockFlattener,
        private SiblingFieldsExtractor $siblingFieldsExtractor,
        private LlmPromptBuilder $promptBuilder,
        private AiClientInterface $aiClient,
        private ToolRegistry $toolRegistry,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * @param array{content_id: int, attributes: array{novaseometas: array{metaKeys?: array}}} $params
     */
    public function generateAndApply(int $contentId, array $params): AgentResponse
    {
        $contentService = $this->repository->getContentService();

        try {
            $content = $contentService->loadContent($contentId);
        } catch (NotFoundException | UnauthorizedException $e) {
            $this->aiLogger->warning(
                '[Agent] Could not load content {id} for SEO update: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return AgentResponse::error(sprintf('Could not load content with ID %d.', $contentId));
        }

        $languageCode = $content->contentInfo->mainLanguageCode;
        $blockText = $this->blockFlattener->flatten($content, $languageCode);

        $contentType = $content->getContentType();
        $siblingFields = $this->siblingFieldsExtractor->extract(
            $content,
            $contentType,
            'novaseometas',
            $languageCode,
        );

        $systemPrompt = $this->promptBuilder->buildSeoSystemPrompt(
            $contentType->getName(),
            $content->contentInfo->name ?? '',
            $blockText,
            $siblingFields,
            $params['attributes']['novaseometas']['metaKeys'] ?? [],
        );

        $userPrompt = 'Generate SEO metadata for this page based on the content provided.';

        try {
            $seoResponse = $this->aiClient->suggest($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(
                '[Agent] SEO generation failed for content {id}: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return AgentResponse::error('Failed to generate SEO metadata. Please try again.');
        }

        $seoData = json_decode($seoResponse, true);
        if (!is_array($seoData)) {
            $this->aiLogger->warning(
                '[Agent] Invalid SEO response for content {id}: {response}',
                ['id' => $contentId, 'response' => $seoResponse],
            );

            return AgentResponse::error('Failed to parse SEO metadata. Please try again.');
        }

        $this->blockFlattener->clearCache($contentId);

        $updateTool = $this->toolRegistry->get(ToolName::UPDATE_CONTENT);
        if ($updateTool === null) {
            return AgentResponse::error('update_content tool is not available.');
        }

        $updateParams = array_merge($params, [
            'content_id' => $contentId,
            'attributes' => ['novaseometas' => $seoData],
        ]);

        $result = $updateTool->execute($updateParams);

        return AgentResponse::withResults([$result], $result->message);
    }
}
