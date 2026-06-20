<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

/**
 * Typed result wrapper for OrchestratorTool::execute().
 *
 * Three shapes:
 * - Control-flow: terminal (ask_user with options, cancel)
 * - State-mutating: caller must persist the deltas (ask_user stores pendingQuestion, propose_plan stores proposedPlan)
 * - Final: execution complete, return to user as AgentResponse
 *
 * Mutations are returned as deltas (just the new pendingQuestion or proposedPlan),
 * NOT as a full WizardState — this avoids losing the tool result message that
 * was just appended to the message history.
 */
final class OrchestratorResponse
{
    /**
     * @param array<string, mixed> $data   Tool-specific payload
     * @param array<int, array{label: string, value: string}>|null $options
     * @param array<string, mixed>|null $pendingQuestionDelta  Set when ask_user saves a pending question
     * @param array<string, mixed>|null $proposedPlanDelta     Set when propose_plan stores a plan
     */
    private function __construct(
        public readonly bool   $success,
        public readonly string $message,
        public readonly array  $data = [],
        public readonly ?array $options = null,
        public readonly ?array $pendingQuestionDelta = null,
        public readonly ?array $proposedPlanDelta = null,
        public readonly bool   $isTerminal = false,
        public readonly bool   $isCancel = false,
    ) {
    }

    public static function ok(string $message, array $data = []): self
    {
        return new self(success: true, message: $message, data: $data);
    }

    public static function error(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * ask_user: store pending question + exit the loop
     *
     * @param array<int, array{label: string, value: string}> $options
     */
    public static function askUser(string $question, array $options): self
    {
        return new self(
            success: true,
            message: $question,
            options: $options,
            pendingQuestionDelta: ['question' => $question, 'options' => $options],
            isTerminal: true,
        );
    }

    /**
     * propose_plan: store the plan + stay in loop (await approval)
     *
     * @param array<string, mixed> $planData
     */
    public static function proposePlan(string $summary, array $planData): self
    {
        return new self(
            success: true,
            message: $summary,
            proposedPlanDelta: $planData,
        );
    }

    public static function cancel(): self
    {
        return new self(
            success: true,
            message: 'Cancelled.',
            isCancel: true,
            isTerminal: true,
        );
    }
}
