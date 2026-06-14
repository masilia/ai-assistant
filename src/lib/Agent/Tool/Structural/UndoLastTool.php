<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class UndoLastTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'undo_last';
    }

    public function getDescription(): string
    {
        return 'Undo the last operation by restoring trashed content or trashing created content.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_ids' => [
                    'type' => 'array',
                    'description' => 'Content IDs to undo (restore from trash)',
                    'items' => ['type' => 'integer'],
                ],
                'location_ids' => [
                    'type' => 'array',
                    'description' => 'Location IDs to undo (restore from trash)',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'required' => ['content_ids'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentService = $this->repository->getContentService();
            $trashService = $this->repository->getTrashService();

            $restored = [];
            $contentIds = $params['content_ids'] ?? [];

            foreach ($contentIds as $contentId) {
                try {
                    // Try to load from trash
                    $trashedItems = $trashService->findByParentLocationId(2); // Root trash
                    foreach ($trashedItems as $trashed) {
                        if ($trashed->contentId === (int) $contentId) {
                            $trashService->restore($trashed);
                            $restored[] = (int) $contentId;
                            break;
                        }
                    }
                } catch (\Throwable) {
                    // Content might not be in trash, skip
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
            return ToolResult::error(sprintf('Failed to undo: %s', $e->getMessage()));
        }
    }
}
