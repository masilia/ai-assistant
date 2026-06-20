<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

/**
 * Exposes the block type catalog to the LLM.
 *
 * Returns all available block types with their fields and capabilities.
 */
final readonly class ListBlockTypesTool implements ToolInterface
{
    public function __construct(
        private BlockCatalog $blockCatalog,
    ) {
    }

    public function getName(): string
    {
        return ToolName::LIST_BLOCKS;
    }

    public function getDescription(): string
    {
        return 'List all available block types with their fields and capabilities. Use this to see what blocks can be added to a page.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $params): ToolResult
    {
        $blocks = $this->blockCatalog->getAvailableBlocks();

        return ToolResult::ok(
            sprintf('Found %d block types', count($blocks)),
            ['blocks' => $blocks],
        );
    }
}
