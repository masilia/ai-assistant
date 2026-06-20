<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Wizard;

use Masilia\AiAssistant\Agent\Wizard\WizardState;
use PHPUnit\Framework\TestCase;

final class WizardStateTest extends TestCase
{
    public function testDefaultStateIsEmpty(): void
    {
        $state = new WizardState();

        self::assertSame([], $state->messages);
        self::assertNull($state->pendingQuestion);
        self::assertNull($state->proposedPlan);
        self::assertSame(0, $state->turns);
        self::assertFalse($state->hasPendingQuestion());
        self::assertFalse($state->hasProposedPlan());
    }

    public function testWithUserMessageAppendsToHistory(): void
    {
        $state = new WizardState();
        $state = $state->withUserMessage('hello');

        self::assertCount(1, $state->messages);
        self::assertSame('user', $state->messages[0]['role']);
        self::assertSame('hello', $state->messages[0]['content']);
    }

    public function testWithAssistantMessageAppendsToHistory(): void
    {
        $state = new WizardState();
        $state = $state->withUserMessage('hello');
        $state = $state->withAssistantMessage('hi there');

        self::assertCount(2, $state->messages);
        self::assertSame('assistant', $state->messages[1]['role']);
    }

    public function testWithToolResultAppendsToHistory(): void
    {
        $state = new WizardState();
        $state = $state->withToolResult('call_123', '{"result": "ok"}');

        self::assertCount(1, $state->messages);
        self::assertSame('tool', $state->messages[0]['role']);
        self::assertSame('call_123', $state->messages[0]['tool_call_id']);
    }

    public function testWithPendingQuestionSetsQuestion(): void
    {
        $state = new WizardState();
        $state = $state->withPendingQuestion('Which location?', [
            ['label' => 'Home', 'value' => '123'],
        ]);

        self::assertTrue($state->hasPendingQuestion());
        self::assertSame('Which location?', $state->pendingQuestion['question']);
        self::assertCount(1, $state->pendingQuestion['options']);
    }

    public function testWithProposedPlanSetsPlan(): void
    {
        $state = new WizardState();
        $state = $state->withProposedPlan(['intent' => 'create_content', 'title' => 'Test']);

        self::assertTrue($state->hasProposedPlan());
        self::assertSame('create_content', $state->proposedPlan['intent']);
    }

    public function testClearPendingRemovesQuestionAndPlan(): void
    {
        $state = new WizardState();
        $state = $state->withPendingQuestion('Q', []);
        $state = $state->withProposedPlan(['intent' => 'x']);

        self::assertTrue($state->hasPendingQuestion());
        self::assertTrue($state->hasProposedPlan());

        $state = $state->clearPending();

        self::assertFalse($state->hasPendingQuestion());
        self::assertFalse($state->hasProposedPlan());
    }

    public function testResetReturnsEmptyState(): void
    {
        $state = new WizardState();
        $state = $state->withUserMessage('hello');

        $reset = $state->reset();

        self::assertSame([], $reset->messages);
    }

    public function testWithTurnIncrementsCounter(): void
    {
        $state = new WizardState();
        $state = $state->withTurn();
        $state = $state->withTurn();

        self::assertSame(2, $state->turns);
    }

    public function testMessageCapDropsOldestNonSystemMessages(): void
    {
        $state = new WizardState();

        $state = $state->withUserMessage('');
        $messages = array_merge(
            [['role' => 'system', 'content' => 'test']],
            $state->messages,
        );
        $clone = clone $state;
        $clone->messages = $messages;
        $state = $clone;

        for ($i = 0; $i < 21; $i++) {
            $state = $state->withUserMessage("msg $i");
        }

        self::assertSame('system', $state->messages[0]['role']);
        self::assertCount(21, $state->messages);
        self::assertSame('msg 1', $state->messages[1]['content']);
    }

    public function testWithAssistantToolCallsAppendsCorrectly(): void
    {
        $state = new WizardState();
        $state = $state->withAssistantToolCalls('thinking...', [
            ['id' => 'call_1', 'name' => 'browse', 'arguments' => []],
        ]);

        self::assertCount(1, $state->messages);
        self::assertSame('assistant', $state->messages[0]['role']);
        self::assertSame('thinking...', $state->messages[0]['content']);
        self::assertCount(1, $state->messages[0]['tool_calls']);
    }
}
