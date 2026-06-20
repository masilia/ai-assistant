<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Result of a tool-capable LLM call.
 *
 * Either the LLM returned plain text (no tool use), or it requested
 * one or more tool calls. Both can appear in the same response
 * (text + tool calls).
 */
final readonly class ToolCallResult
{
    /**
     * @param ToolCall[] $toolCalls
     */
    public function __construct(
        public ?string $text = null,
        public array   $toolCalls = [],
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function hasText(): bool
    {
        return $this->text !== null && $this->text !== '';
    }
}
