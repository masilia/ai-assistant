<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

readonly class AgentPlan
{
    /**
     * @param array<int, array{tool: string, params: array<string, mixed>, description: string}> $steps
     */
    public function __construct(
        public array  $steps,
        public string $description = '',
        public bool   $requiresApproval = true,
    ) {
    }

    /**
     * @return array{steps: array, description: string, requiresApproval: bool}
     */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'description' => $this->description,
            'requiresApproval' => $this->requiresApproval,
        ];
    }
}
