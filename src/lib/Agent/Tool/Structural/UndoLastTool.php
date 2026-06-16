<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Agent\Tool\TrashRestorer;
use Psr\Log\LoggerInterface;

readonly class UndoLastTool implements ToolInterface
{
    public function __construct(
        private TrashRestorer $restorer,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::UNDO_LAST_OPERATION;
    }

    public function getDescription(): string
    {
        return 'Restore one or more trashed content items back to their original location.';
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
            ],
            'required' => ['content_ids'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $restored = $this->restorer->restore($params['content_ids'] ?? []);
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('undo_last_operation');
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'undo_last_operation');
        }

        return ToolResult::ok(
            sprintf('Restored %d items', count($restored)),
            [
                'restored' => $restored,
                'count' => count($restored),
            ],
        );
    }
}
