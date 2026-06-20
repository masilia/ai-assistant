<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class AgentResponse
{
    /**
     * @param ToolResult[] $results
     * @param array<int, array{label: string, value: string}>|null $options
     */
    public function __construct(
        public string $message,
        public array  $results = [],
        public bool   $success = true,
        public ?array $options = null,
    ) {
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
     * @return array{message: string, results: array, success: bool, options: array|null}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'results' => array_map(
                static fn(ToolResult $r) => $r->toArray(),
                $this->results,
            ),
            'success' => $this->success,
            'options' => $this->options,
        ];
    }
}
