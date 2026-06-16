<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentPublishHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class UpdateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentPublishHelper $publishHelper,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::UPDATE_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Update fields on an existing content item.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to update',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Field values to update as key-value pairs',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['content_id', 'attributes'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentId = (int) $params['content_id'];
            $attributes = $params['attributes'] ?? [];
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            $published = $this->publishHelper->updateFields($contentId, $attributes, $languageCode);

            return ToolResult::ok(
                sprintf('Updated content %d', $contentId),
                [
                    'content_id' => $published->id,
                    'version_no' => $published->versionInfo->versionNo,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'update content');
        }
    }
}
