<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class RestoreContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::RESTORE_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Restore specific trashed content items.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_ids' => [
                    'type' => 'array',
                    'description' => 'Content IDs to restore from trash',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'required' => ['content_ids'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $trashService = $this->repository->getTrashService();

            $restored = [];
            $contentIds = $params['content_ids'] ?? [];

            $trashedItems = $trashService->findTrashItems(new Query([
                'filter' => new Query\Criterion\ContentId($contentIds),
            ]));

            foreach ($trashedItems->items as $trashed) {
                $trashService->recover($trashed);
                $restored[] = $trashed->contentId;
            }

            return ToolResult::ok(
                sprintf('Restored %d items', count($restored)),
                [
                    'restored' => $restored,
                    'count' => count($restored),
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'restore content');
        }
    }
}
