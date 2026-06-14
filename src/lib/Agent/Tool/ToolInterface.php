<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Returns JSON Schema for the tool's parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * @param array<string, mixed> $params
     */
    public function execute(array $params): ToolResult;
}
