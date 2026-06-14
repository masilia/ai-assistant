<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class TrashContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'trash_content';
    }

    public function getDescription(): string
    {
        return 'Move content to trash. Can be restored later with undo_last.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to trash',
                ],
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to trash (optional if content_id provided)',
                ],
            ],
            'required' => ['content_id'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $result = $this->repository->sudo(function () use ($params) {
                $contentService = $this->repository->getContentService();
                $locationService = $this->repository->getLocationService();
                $trashService = $this->repository->getTrashService();

                $contentId = (int) $params['content_id'];
                $content = $contentService->loadContent($contentId);

                $locationId = isset($params['location_id'])
                    ? (int) $params['location_id']
                    : $content->contentInfo->mainLocationId;

                $location = $locationService->loadLocation($locationId);
                $trashService->trash($location);

                return [
                    'content_id' => $contentId,
                    'location_id' => $locationId,
                    'trashed' => true,
                ];
            });

            return ToolResult::ok(
                sprintf('Trashed content %d', $result['content_id']),
                $result,
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to trash content: %s', $e->getMessage()));
        }
    }
}
