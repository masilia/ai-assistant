<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Agent\Orchestrator\OrchestratorResponse;
use Masilia\AiAssistant\Agent\Orchestrator\OrchestratorTool;
use Masilia\AiAssistant\Agent\Orchestrator\WorkerContext;
use Masilia\AiAssistant\Agent\Wizard\WizardState;
use Masilia\AiAssistant\Agent\Wizard\WizardStoreInterface;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\Client\ToolCallResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Owns the orchestrator-driven agent loop.
 *
 * The LLM sees only 4 control tools: ask_user, explore_site, propose_plan, cancel.
 * Heavy lifting (siteaccess discovery, block layout, plan validation, execution)
 * is done by deterministic Worker classes — the LLM never sees those internals.
 *
 * The LLM is the ONLY decision-maker. This class does not classify user intent
 * with static keywords. Instead:
 * - Approval is signalled by the LLM re-invoking `propose_plan` with the same
 *   arguments — `ProposePlanTool` detects the re-invocation and executes.
 * - Cancellation is signalled by the LLM calling the `cancel` tool — the tool
 *   itself clears wizard state.
 * - New requests after a proposed plan just add the message; the LLM sees the
 *   prior plan in context and decides whether to re-propose, modify, or cancel.
 *
 * This replaces AgentRunner, which exposed all 16 raw tools to the LLM and
 * caused looping behavior.
 */
final readonly class AgentOrchestrator
{
    private const MAX_ITERATIONS = 8;

    public function __construct(
        private AiClientInterface    $aiClient,
        private WizardStoreInterface $wizardStore,
        private LoggerInterface      $aiLogger,
        /** @var iterable<OrchestratorTool> */
        private iterable             $tools,
    )
    {
    }

    public function run(int $userId, string $message, ?string $selectedOption = null): AgentResponse
    {
        $state = $this->wizardStore->get($userId) ?? new WizardState();

        // ALWAYS just add the user message and let the LLM decide what to do.
        // The LLM sees the full conversation context (previous tool calls,
        // proposed plans, pending questions) and responds appropriately:
        // - re-calls propose_plan with same args to approve (→ execute)
        // - re-calls propose_plan with new args to modify (→ execute modified plan)
        // - calls cancel() to abandon
        // - calls ask_user to clarify
        $effectiveMessage = $selectedOption ?? $message;
        $state = $state->withUserMessage($effectiveMessage);

        $this->wizardStore->put($userId, $state);

        return $this->runLoop($userId, $state);
    }

    private function runLoop(int $userId, WizardState $state): AgentResponse
    {
        $tools = $this->indexTools();
        $toolDefinitions = $this->buildToolDefinitions($tools);

        $systemPrompt = AgentSystemPrompt::get();
        if ($state->messages === [] || ($state->messages[0]['role'] ?? '') !== 'system') {
            $state = $this->prependSystemPrompt($state, $systemPrompt);
        }

        $this->aiLogger->debug('[AgentOrchestrator] Loop start: {tools} control tools, {msgs} messages', [
            'tools' => count($toolDefinitions),
            'msgs' => count($state->messages),
        ]);

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $this->aiLogger->debug('[AgentOrchestrator] Iteration {i}/{max}', [
                'i' => $i + 1,
                'max' => self::MAX_ITERATIONS,
            ]);

            try {
                $result = $this->aiClient->chatWithTools($state->messages, $toolDefinitions);
            } catch (Throwable $e) {
                $this->aiLogger->error('[AgentOrchestrator] LLM call failed: {message}', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $this->wizardStore->clear($userId);

                return AgentResponse::error(
                    'The AI service encountered an error. Please try again later.',
                );
            }

            $this->aiLogger->debug('[AgentOrchestrator] LLM response: {toolCount} tool calls, hasText={hasText}', [
                'toolCount' => count($result->toolCalls),
                'hasText' => $result->hasText(),
                'textPreview' => $result->hasText() ? substr($result->text, 0, 200) : '',
                'toolNames' => array_map(fn($c) => $c->name, $result->toolCalls),
            ]);

            $outcome = $this->handleResponse($userId, $state, $result, $tools);

            if ($outcome['response'] !== null) {
                return $outcome['response'];
            }

            $state = $outcome['state'];
        }

        $this->aiLogger->warning('[AgentOrchestrator] Loop exhausted {max} iterations', [
            'max' => self::MAX_ITERATIONS,
        ]);
        $this->wizardStore->clear($userId);

        return AgentResponse::error('I got stuck after several attempts. Please rephrase your request.');
    }

    private function indexTools(): array
    {
        $indexed = [];
        foreach ($this->tools as $tool) {
            $indexed[$tool->getName()] = $tool;
        }
        return $indexed;
    }

    private function buildToolDefinitions(array $tools): array
    {
        $defs = [];
        foreach ($tools as $tool) {
            $defs[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }
        return $defs;
    }

    private function prependSystemPrompt(WizardState $state, string $systemPrompt): WizardState
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $state->messages,
        );
        $clone = clone $state;
        $clone->messages = $messages;
        return $clone;
    }

    /**
     * Process a single LLM response. Returns:
     * - ['response' => AgentResponse, 'state' => null]   if the loop should exit
     * - ['response' => null,        'state' => WizardState] if the loop should continue
     */
    private function handleResponse(
        int            $userId,
        WizardState    $state,
        ToolCallResult $result,
        array          $tools,
    ): array
    {
        // No tool calls → text reply (terminal)
        if (!$result->hasToolCalls()) {
            if ($result->hasText()) {
                $newState = $state->withAssistantMessage($result->text);
                $this->wizardStore->put($userId, $newState);

                return ['response' => new AgentResponse(message: $result->text), 'state' => $newState];
            }

            // Empty response — let the loop continue (or eventually exhaust)
            return ['response' => null, 'state' => $state];
        }

        // Append assistant message (with tool calls) to history.
        // Use OpenAI-compatible format: {id, type: "function", function: {name, arguments}}.
        // The arguments value must be a JSON string per the API spec.
        $state = $state->withAssistantToolCalls(
            $result->text ?? '',
            array_map(static fn($c) => [
                'id' => $c->id,
                'type' => 'function',
                'function' => [
                    'name' => $c->name,
                    'arguments' => is_string($c->arguments) ? $c->arguments : json_encode($c->arguments, JSON_THROW_ON_ERROR),
                ],
            ], $result->toolCalls),
        );

        $exitResponse = null;

        foreach ($result->toolCalls as $call) {
            $this->aiLogger->debug('[AgentOrchestrator] Calling tool: {name}', ['name' => $call->name]);

            $tool = $tools[$call->name] ?? null;
            if ($tool === null) {
                $state = $state->withToolResult($call->id, json_encode(['error' => "Unknown tool: {$call->name}"], JSON_THROW_ON_ERROR));
                continue;
            }

            $context = new WorkerContext($userId, $state);
            try {
                $toolResponse = $tool->execute($call->arguments, $context);
            } catch (Throwable $e) {
                $this->aiLogger->error('[AgentOrchestrator] Tool {name} threw: {message}', [
                    'name' => $call->name,
                    'message' => $e->getMessage(),
                ]);
                $state = $state->withToolResult($call->id, json_encode(['error' => $e->getMessage()]));
                continue;
            }

            $state = $this->applyOrchestratorResponse($state, $toolResponse, $call->id, $userId);

            if ($toolResponse->isTerminal) {
                $exitResponse = $this->buildExitResponse($toolResponse);
                break;
            }
        }

        if ($exitResponse !== null) {
            return ['response' => $exitResponse, 'state' => $state];
        }

        $this->wizardStore->put($userId, $state);
        return ['response' => null, 'state' => $state];
    }

    private function applyOrchestratorResponse(
        WizardState          $state,
        OrchestratorResponse $response,
        string               $toolCallId,
        int                  $userId,
    ): WizardState
    {
        // 1. Always append the tool result message to history (preserves the assistant+tool pairing
        //    that Anthropic/MiniMax requires for the next iteration's tool_use_id matching).
        $payload = [
            'success' => $response->success,
            'message' => $response->message,
            'data' => $response->data,
        ];
        $state = $state->withToolResult($toolCallId, json_encode($payload, JSON_THROW_ON_ERROR));

        // 2. Apply deltas (pendingQuestion / proposedPlan) to the current state.
        //    Deltas are merged, NOT a full state replacement, so the message history
        //    (including the tool result we just appended) is preserved.
        $stateMutated = false;
        if ($response->pendingQuestionDelta !== null) {
            $state = $state->withPendingQuestion(
                $response->pendingQuestionDelta['question'] ?? '',
                $response->pendingQuestionDelta['options'] ?? [],
            );
            $stateMutated = true;
        }
        if ($response->proposedPlanDelta !== null) {
            $state = $state->withProposedPlan($response->proposedPlanDelta);
            $stateMutated = true;
        }

        // 3. Persist mutated state so subsequent requests see it.
        if ($stateMutated) {
            $this->wizardStore->put($userId, $state);
        }

        // 4. Cancel clears the state immediately.
        if ($response->isCancel) {
            $this->wizardStore->clear($userId);
        }

        return $state;
    }

    private function buildExitResponse(OrchestratorResponse $toolResponse): AgentResponse
    {
        if ($toolResponse->isCancel) {
            return AgentResponse::error('Wizard cancelled. How can I help you?');
        }

        if ($toolResponse->options !== null) {
            return new AgentResponse(
                message: $toolResponse->message,
                options: $toolResponse->options,
            );
        }

        if (!$toolResponse->success) {
            return AgentResponse::error($toolResponse->message);
        }

        return new AgentResponse(message: $toolResponse->message);
    }
}
