<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Agent\Tool\TrashRestorer;
use Psr\Log\LoggerInterface;

readonly class RestoreContentTool implements ToolInterface
{
    public function __construct(
        private TrashRestorer $restorer,
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
            $restored = $this->restorer->restore($params['content_ids'] ?? []);
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'restore content');
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
