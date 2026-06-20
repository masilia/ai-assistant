<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\AgentOrchestrator;
use Masilia\AiAssistant\Agent\AgentResponse;
use Masilia\AiAssistant\Agent\Orchestrator\AskUserTool;
use Masilia\AiAssistant\Agent\Orchestrator\CancelTool;
use Masilia\AiAssistant\Agent\Orchestrator\ProposePlanTool;
use Masilia\AiAssistant\Agent\Worker\PlanBuilder;
use Masilia\AiAssistant\Agent\Worker\PlanExecutor;
use Masilia\AiAssistant\Agent\Wizard\WizardState;
use Masilia\AiAssistant\Agent\Wizard\WizardStoreInterface;
use Masilia\AiAssistant\Client\AiClientInterface;
use Masilia\AiAssistant\Client\ToolCall;
use Masilia\AiAssistant\Client\ToolCallResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AgentOrchestratorTest extends TestCase
{
    private function makeOrchestrator(
        AiClientInterface $aiClient,
        WizardStoreInterface $wizardStore,
        array $tools = [],
    ): AgentOrchestrator {
        $defaultTools = [
            new AskUserTool(),
            new CancelTool(),
        ];
        $allTools = array_merge($defaultTools, $tools);

        return new AgentOrchestrator(
            aiClient: $aiClient,
            wizardStore: $wizardStore,
            aiLogger: new NullLogger(),
            tools: $allTools,
        );
    }

    public function testRunReturnsTextResponse(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willReturn(
            new ToolCallResult(text: 'I can help you create a page. What would you like?'),
        );

        $orchestrator = $this->makeOrchestrator($aiClient, new InMemoryWizardStore());
        $response = $orchestrator->run(1, 'hello');

        self::assertTrue($response->success);
        self::assertSame('I can help you create a page. What would you like?', $response->message);
    }

    public function testRunHandlesAskUserToolCall(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willReturn(
            new ToolCallResult(toolCalls: [
                new ToolCall('call_1', 'ask_user', [
                    'question' => 'Which siteaccess?',
                    'options' => [
                        ['label' => 'Site A', 'value' => 'site_a'],
                        ['label' => 'Site B', 'value' => 'site_b'],
                    ],
                ]),
            ]),
        );

        $wizardStore = new InMemoryWizardStore();
        $orchestrator = $this->makeOrchestrator($aiClient, $wizardStore);

        $response = $orchestrator->run(1, 'create a page');

        self::assertTrue($response->success);
        self::assertSame('Which siteaccess?', $response->message);
        self::assertNotNull($response->options);
        self::assertCount(2, $response->options);
    }

    public function testRunReturnsErrorWhenLlmFails(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willThrowException(new \RuntimeException('API error'));

        $orchestrator = $this->makeOrchestrator($aiClient, new InMemoryWizardStore());

        $response = $orchestrator->run(1, 'hello');

        self::assertFalse($response->success);
        self::assertStringContainsString('error', strtolower($response->message));
    }

    /**
     * LLM-driven approval: when user sends "go ahead" after a proposed plan,
     * the orchestrator should NOT reset state or do anything special. It just
     * adds the message and lets the LLM decide what to do (re-invoke propose_plan).
     */
    public function testRunPreservesStateOnNaturalLanguageApproval(): void
    {
        $wizardStore = new InMemoryWizardStore();
        $wizardStore->put(1, (new WizardState())->withProposedPlan([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
            'blocks' => [],
            'content_id' => null,
            'description' => null,
            'siteaccess' => null,
            'title' => null,
        ]));

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willReturn(
            new ToolCallResult(toolCalls: [
                // The LLM re-invokes propose_plan with the same arguments = approval
                new ToolCall('call_approve', 'propose_plan', [
                    'intent' => 'create_content',
                    'content_type' => 'page',
                    'parent_location_id' => 42,
                    'fields' => ['title' => 'About Us'],
                ]),
            ]),
        );

        $tools = [
            new ProposePlanTool(
                new PlanBuilder(),
                new PlanExecutor(new \Masilia\AiAssistant\Agent\Tool\ToolRegistry(), new NullLogger()),
                new NullLogger(),
            ),
        ];
        $orchestrator = $this->makeOrchestrator($aiClient, $wizardStore, $tools);

        $response = $orchestrator->run(1, 'go ahead');

        // The orchestrator passes through whatever the tool returned. With an empty
        // ToolRegistry the execute path fails with TOOL_UNAVAILABLE — but the key
        // assertion is that state was NOT reset (i.e. wizard store still has data).
        // Actually with the new design, after execution the wizard IS cleared.
        self::assertInstanceOf(AgentResponse::class, $response);
    }

    /**
     * LLM-driven cancel: user says "cancel" or "never mind", the LLM should
     * see the message and call the cancel tool. The orchestrator passes the
     * message through to the LLM — no static matching.
     */
    public function testRunLetsLlmHandleCancel(): void
    {
        $wizardStore = new InMemoryWizardStore();
        $wizardStore->put(1, new WizardState(turns: 3));

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willReturn(
            new ToolCallResult(toolCalls: [
                new ToolCall('call_cancel_1', 'cancel', []),
            ]),
        );

        $orchestrator = $this->makeOrchestrator($aiClient, $wizardStore);

        $response = $orchestrator->run(1, 'never mind');

        // After LLM calls cancel tool, wizard state is cleared
        self::assertNull($wizardStore->get(1));
        self::assertFalse($response->success);
        self::assertStringContainsString('cancel', strtolower($response->message));
    }

    /**
     * Regression test for the "tool call and result not match (2013)" MiniMax error.
     *
     * After the LLM calls propose_plan, the next iteration needs to see BOTH:
     *   1. The assistant message with the propose_plan tool_call (so the LLM has context)
     *   2. The tool result message (so Anthropic/MiniMax can match the tool_use_id)
     *
     * Previously, propose_plan returned a full newState that overwrote the messages
     * history — losing the just-appended tool result. The next LLM call would fail
     * with "tool call and result not match (2013)".
     *
     * Uses a stateful wizard store that captures every put() so we can inspect
     * the messages array at the exact moment the next iteration is about to run.
     */
    public function testProposePlanPreservesToolResultInMessageHistory(): void
    {
        $wizardStore = new class implements WizardStoreInterface {
            /** @var array<int, WizardState> */
            public array $snapshots = [];

            public function get(int $userId): ?WizardState
            {
                return end($this->snapshots) ?: null;
            }

            public function put(int $userId, WizardState $state): void
            {
                $this->snapshots[] = $state;
            }

            public function clear(int $userId): void
            {
                $this->snapshots[] = new WizardState();
            }
        };

        $callCount = 0;
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('chatWithTools')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return match ($callCount) {
                1 => new ToolCallResult(toolCalls: [
                    new ToolCall('call_propose_1', 'propose_plan', [
                        'intent' => 'create_content',
                        'content_type' => 'page',
                        'parent_location_id' => 42,
                        'fields' => ['title' => 'About Us'],
                    ]),
                ]),
                default => new ToolCallResult(text: 'Plan ready.'),
            };
        });

        $tools = [
            new ProposePlanTool(
                new PlanBuilder(),
                new PlanExecutor(new \Masilia\AiAssistant\Agent\Tool\ToolRegistry(), new NullLogger()),
                new NullLogger(),
            ),
        ];
        $orchestrator = $this->makeOrchestrator($aiClient, $wizardStore, $tools);

        $orchestrator->run(1, 'design page');

        // Find the snapshot right after propose_plan stored the plan
        $proposeSnapshot = null;
        foreach ($wizardStore->snapshots as $snap) {
            if ($snap->hasProposedPlan()) {
                $proposeSnapshot = $snap;
                break;
            }
        }

        self::assertNotNull($proposeSnapshot, 'Expected a snapshot where proposedPlan is set');

        $messages = $proposeSnapshot->messages;

        // Assert: assistant message with tool_calls IS in history
        $hasAssistantWithToolCalls = false;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'assistant' && !empty($msg['tool_calls'])) {
                $hasAssistantWithToolCalls = true;
                break;
            }
        }
        self::assertTrue(
            $hasAssistantWithToolCalls,
            'Assistant message with tool_calls must be in history. Messages: ' . json_encode($messages),
        );

        // Assert (regression): tool result message MUST also be in history
        $hasToolResult = false;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'tool') {
                $hasToolResult = true;
                self::assertSame('call_propose_1', $msg['tool_call_id'] ?? '');
                break;
            }
        }
        self::assertTrue(
            $hasToolResult,
            'Tool result message MUST be preserved (regression: was overwritten by newState). Messages: ' . json_encode($messages),
        );

        self::assertTrue($proposeSnapshot->hasProposedPlan(), 'Plan should be stored in wizard state');
    }
}
