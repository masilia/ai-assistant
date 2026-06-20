<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

/**
 * Save a pending question to wizard state and return it for the user.
 *
 * This is a TERMINAL tool — when the LLM calls it, the loop exits
 * and the user is shown the question.
 */
final readonly class AskUserTool implements OrchestratorTool
{
    public function getName(): string
    {
        return 'ask_user';
    }

    public function getDescription(): string
    {
        return 'Ask the user a clarifying question. Use this ONLY when the user has not given you enough information to propose a plan (e.g. which siteaccess to use, or which parent location). When you already have what you need, call propose_plan instead.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => [
                    'type' => 'string',
                    'description' => 'The question to ask the user',
                ],
                'options' => [
                    'type' => 'array',
                    'description' => 'Optional list of choices. If omitted, the user can type a free-form answer.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'description' => 'Display label'],
                            'value' => ['type' => 'string', 'description' => 'Value to return when selected'],
                        ],
                        'required' => ['label', 'value'],
                    ],
                ],
            ],
            'required' => ['question'],
        ];
    }

    public function execute(array $arguments, WorkerContext $context): OrchestratorResponse
    {
        $question = (string) ($arguments['question'] ?? '');
        if ($question === '') {
            return OrchestratorResponse::error('ask_user requires a question');
        }

        $options = $arguments['options'] ?? [];
        $normalized = [];
        foreach ($options as $opt) {
            if (!is_array($opt) || !isset($opt['label'], $opt['value'])) {
                continue;
            }
            $normalized[] = [
                'label' => (string) $opt['label'],
                'value' => (string) $opt['value'],
            ];
        }

        return OrchestratorResponse::askUser($question, $normalized);
    }
}
