<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class UndoLastTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
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
            $trashService = $this->repository->getTrashService();

            $restored = [];
            $errors = [];
            $contentIds = $params['content_ids'] ?? [];

            foreach ($contentIds as $contentId) {
                try {
                    $trashedItems = $trashService->findByParentLocationId(2);
                    foreach ($trashedItems as $trashed) {
                        if ($trashed->contentId === (int) $contentId) {
                            $trashService->restore($trashed);
                            $restored[] = (int) $contentId;
                            break;
                        }
                    }
                } catch (NotFoundException $e) {
                    $this->aiLogger->warning('[Agent] undo_last: content {id} not found in trash', [
                        'id' => $contentId,
                        'message' => $e->getMessage(),
                    ]);
                    $errors[] = ['content_id' => $contentId, 'error' => 'not_found'];
                } catch (UnauthorizedException $e) {
                    $this->aiLogger->warning('[Agent] undo_last: permission denied for content {id}', [
                        'id' => $contentId,
                        'message' => $e->getMessage(),
                    ]);
                    $errors[] = ['content_id' => $contentId, 'error' => 'permission_denied'];
                } catch (\Throwable $e) {
                    $this->aiLogger->warning('[Agent] undo_last: failed to restore content {id}', [
                        'id' => $contentId,
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                    $errors[] = ['content_id' => $contentId, 'error' => 'unknown'];
                }
            }

            $data = [
                'restored' => $restored,
                'count' => count($restored),
            ];
            if (!empty($errors)) {
                $data['errors'] = $errors;
            }

            return ToolResult::ok(
                sprintf('Restored %d items', count($restored)),
                $data,
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'undo');
        }
    }
}
