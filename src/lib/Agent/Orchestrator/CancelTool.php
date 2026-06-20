<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

/**
 * Abandon current work and clear wizard state.
 *
 * Used when the user says "cancel", "start over", "nevermind", etc.
 * Terminal — clears state and returns to fresh mode.
 */
final readonly class CancelTool implements OrchestratorTool
{
    public function getName(): string
    {
        return 'cancel';
    }

    public function getDescription(): string
    {
        return 'Cancel current work and clear wizard state. Use when the user wants to start over or abandon the current plan.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, WorkerContext $context): OrchestratorResponse
    {
        return OrchestratorResponse::cancel();
    }
}
