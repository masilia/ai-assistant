<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Wizard;

/**
 * Immutable scratchpad for the LLM-driven agent loop.
 *
 * Stores the conversation message history, pending question, and
 * proposed plan across multiple turns within a single wizard.
 */
final class WizardState
{
    public const MAX_MESSAGES = 20;

    /**
     * @param array  $messages        Full message history (provider-native format, capped at MAX_MESSAGES)
     * @param ?array $pendingQuestion {question: string, options: [{label, value}]}
     * @param ?array $proposedPlan    The plan awaiting user approval
     */
    public function __construct(
        public array  $messages = [],
        public ?array $pendingQuestion = null,
        public ?array $proposedPlan = null,
        public int    $turns = 0,
    ) {
    }

    public function hasPendingQuestion(): bool
    {
        return $this->pendingQuestion !== null;
    }

    public function hasProposedPlan(): bool
    {
        return $this->proposedPlan !== null;
    }

    public function withUserMessage(string $content): self
    {
        return $this->appendMessage(['role' => 'user', 'content' => $content]);
    }

    public function withAssistantMessage(string $content): self
    {
        return $this->appendMessage(['role' => 'assistant', 'content' => $content]);
    }

    /**
     * @param array $toolCalls [{id, name, arguments}]
     */
    public function withAssistantToolCalls(string $text, array $toolCalls): self
    {
        $message = ['role' => 'assistant', 'content' => $text ?: null, 'tool_calls' => $toolCalls];

        return $this->appendMessage($message);
    }

    public function withToolResult(string $toolCallId, string $result): self
    {
        return $this->appendMessage([
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'content' => $result,
        ]);
    }

    public function withPendingQuestion(string $question, array $options): self
    {
        $clone = clone $this;
        $clone->pendingQuestion = [
            'question' => $question,
            'options' => $options,
        ];

        return $clone;
    }

    public function withProposedPlan(array $plan): self
    {
        $clone = clone $this;
        $clone->proposedPlan = $plan;

        return $clone;
    }

    public function clearPending(): self
    {
        $clone = clone $this;
        $clone->pendingQuestion = null;
        $clone->proposedPlan = null;

        return $clone;
    }

    public function reset(): self
    {
        return new self();
    }

    public function withTurn(): self
    {
        $clone = clone $this;
        $clone->turns++;

        return $clone;
    }

    private function appendMessage(array $message): self
    {
        $clone = clone $this;
        $clone->messages[] = $message;

        $systemMessages = [];
        $otherMessages = [];
        foreach ($clone->messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemMessages[] = $msg;
            } else {
                $otherMessages[] = $msg;
            }
        }

        if (count($otherMessages) > self::MAX_MESSAGES) {
            $otherMessages = array_slice($otherMessages, -self::MAX_MESSAGES);
        }

        $clone->messages = array_merge($systemMessages, $otherMessages);

        return $clone;
    }
}
