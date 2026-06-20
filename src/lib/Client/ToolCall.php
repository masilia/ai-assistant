<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * A single tool call requested by the LLM.
 */
final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array  $arguments,
    ) {
    }
}
