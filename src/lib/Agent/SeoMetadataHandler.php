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
use Masilia\AiAssistant\AiConstants;
use Masilia\AiAssistant\NovaSeoPromptBuilder;
use Psr\Log\LoggerInterface;

/**
 * Generates SEO metadata via the LLM and applies it to a content item.
 *
 * Flow: load content → flatten blocks → extract sibling fields →
 * build SEO prompt → call LLM → parse JSON → return data for plan.
 */
readonly class SeoMetadataHandler
{
    public function __construct(
        private Repository $repository,
        private BlockFlattener $blockFlattener,
        private SiblingFieldsExtractor $siblingFieldsExtractor,
        private NovaSeoPromptBuilder $novaSeo,
        private AiClientInterface $aiClient,
        private ToolRegistry $toolRegistry,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * Generate SEO metadata for a content item and return it as a preview.
     *
     * Does NOT write to the database. The caller should return an AgentPlan
     * so the user can review and confirm before applying.
     *
     * @return array{novaseometas: array}|null  The parsed SEO data, or null on error
     */
    public function generateMetadata(int $contentId, array $params): ?array
    {
        $contentService = $this->repository->getContentService();

        try {
            $content = $contentService->loadContent($contentId);
        } catch (NotFoundException | UnauthorizedException $e) {
            $this->aiLogger->warning(
                '[Agent] Could not load content {id} for SEO update: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return null;
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

        $base = "You are a professional content writing assistant for a CMS."
            . " The content type is \"{$contentType->getName()}\"."
            . " You are writing for the field \"SEO Metadata\"."
            . "\n\nContent title: \"{$this->scrubForPrompt($content->contentInfo->name ?? '')}\"."
            . "\n\n{$blockText}";

        if (!empty($siblingFields)) {
            $base .= "\n\nOther fields already filled in this content item (use for context, do not repeat):";
            foreach ($siblingFields as $field) {
                $label = $this->scrubForPrompt($field->label);
                $value = $this->scrubForPrompt(mb_substr($field->value, 0, AiConstants::MAX_SIBLING_CHARS));
                if ($label !== '' && $value !== '') {
                    $base .= sprintf("\n  - %s: \"%s\"", $label, $value);
                }
            }
        }

        $metaKeys = $params['attributes']['novaseometas']['metaKeys'] ?? [];
        $systemPrompt = $this->novaSeo->wholeBlockPrompt($base, $metaKeys);

        $userPrompt = 'Generate SEO metadata for this page based on the content provided.';

        try {
            $seoResponse = $this->aiClient->suggest($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(
                '[Agent] SEO generation failed for content {id}: {message}',
                ['id' => $contentId, 'message' => $e->getMessage()],
            );

            return null;
        }

        $seoData = json_decode($seoResponse, true);
        if (!is_array($seoData)) {
            $this->aiLogger->warning(
                '[Agent] Invalid SEO response for content {id}: {response}',
                ['id' => $contentId, 'response' => $seoResponse],
            );

            return null;
        }

        $this->blockFlattener->clearCache($contentId);

        return ['novaseometas' => $seoData];
    }

    /**
     * Apply pre-generated SEO metadata to a content item.
     *
     * Called after the user confirms the plan.
     */
    public function applyMetadata(int $contentId, array $seoData, array $originalParams): ToolResult
    {
        $updateTool = $this->toolRegistry->get(ToolName::UPDATE_CONTENT);
        if ($updateTool === null) {
            return ToolResult::error('update_content tool is not available.');
        }

        $updateParams = array_merge($originalParams, [
            'content_id' => $contentId,
            'attributes' => $seoData,
        ]);

        return $updateTool->execute($updateParams);
    }

    private function scrubForPrompt(string $value): string
    {
        return AiConstants::scrubForPrompt($value);
    }
}
