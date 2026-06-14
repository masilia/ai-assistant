<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class AgentResponse
{
    /**
     * @param ToolResult[] $results
     */
    public function __construct(
        public string $message,
        public array  $results = [],
        public ?AgentPlan $plan = null,
        public bool   $success = true,
    ) {
    }

    public static function withPlan(AgentPlan $plan, string $message = ''): self
    {
        return new self(
            message: $message ?: 'Here is the plan for your request:',
            plan: $plan,
        );
    }

    public static function withResults(array $results, string $message = ''): self
    {
        $success = true;
        foreach ($results as $result) {
            if (!$result->success) {
                $success = false;
                break;
            }
        }

        return new self(
            message: $message ?: 'Operation completed.',
            results: $results,
            success: $success,
        );
    }

    public static function error(string $message): self
    {
        return new self(
            message: $message,
            success: false,
        );
    }

    /**
     * @return array{message: string, results: array, plan: array|null, success: bool}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'results' => array_map(
                static fn(ToolResult $r) => $r->toArray(),
                $this->results,
            ),
            'plan' => $this->plan?->toArray(),
            'success' => $this->success,
        ];
    }
}
