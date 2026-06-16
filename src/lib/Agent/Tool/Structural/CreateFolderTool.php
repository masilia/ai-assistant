<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentPublishHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\ContentTypeId;
use Masilia\AiAssistant\FieldId;
use Psr\Log\LoggerInterface;

readonly class CreateFolderTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentPublishHelper $publishHelper,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::CREATE_FOLDER;
    }

    public function getDescription(): string
    {
        return 'Create a folder content type in the content tree.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Folder name',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['name', 'parent_location_id'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            $result = $this->publishHelper->createAndPublish(
                ContentTypeId::FOLDER,
                [(int) $params['parent_location_id']],
                [FieldId::NAME => $params['name']],
                $languageCode,
            );

            return ToolResult::ok(
                sprintf('Created folder "%s" (ID: %d)', $params['name'], $result['content']->id),
                [
                    'content_id' => $result['content']->id,
                    'location_id' => $result['location']->id,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'create folder');
        }
    }
}
