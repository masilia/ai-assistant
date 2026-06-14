<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class RestoreContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'restore_content';
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
            $contentService = $this->repository->getContentService();

            $restored = [];
            $contentIds = $params['content_ids'] ?? [];

            // Get all trashed items
            $trashedItems = $contentService->loadContentInfoList($contentIds);

            foreach ($contentIds as $contentId) {
                foreach ($trashedItems as $trashed) {
                    if ($trashed->contentId === (int) $contentId) {
                        $trashService->recover($trashed);
                        $restored[] = (int) $contentId;
                        break;
                    }
                }
            }

            return ToolResult::ok(
                sprintf('Restored %d items', count($restored)),
                [
                    'restored' => $restored,
                    'count' => count($restored),
                ],
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to restore content: %s', $e->getMessage()));
        }
    }
}
