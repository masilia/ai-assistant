<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

/**
 * Contract for the four high-level control tools exposed to the orchestrator LLM.
 *
 * The LLM only sees 4 of these — never the 16 raw agent tools directly.
 * Workers (SiteExplorer, PlanBuilder, PlanExecutor) do the heavy lifting
 * and return structured results.
 */
interface OrchestratorTool
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array<string, mixed> JSON Schema
     */
    public function getParameters(): array;

    /**
     * @param array<string, mixed> $arguments Raw arguments from the LLM
     * @return OrchestratorResponse
     */
    public function execute(array $arguments, WorkerContext $context): OrchestratorResponse;
}
