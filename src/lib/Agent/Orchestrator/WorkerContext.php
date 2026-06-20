<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

use Masilia\AiAssistant\Agent\Wizard\WizardState;

/**
 * Context passed from AgentOrchestrator into OrchestratorTool::execute().
 *
 * Lets a tool update the wizard state (e.g. save a pending question or
 * a proposed plan) without needing direct access to the WizardStore.
 */
final class WorkerContext
{
    public function __construct(
        public readonly int          $userId,
        public readonly WizardState  $state,
    ) {
    }
}
