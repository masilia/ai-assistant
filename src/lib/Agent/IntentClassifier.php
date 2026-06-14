<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Client\AiClientInterface;

readonly class IntentClassifier
{
    private const INTENTS = [
        'create_page',
        'create_content',
        'update_content',
        'delete_content',
        'search_content',
        'list_blocks',
        'undo',
        'set_site',
    ];

    public function __construct(
        private AiClientInterface $aiClient,
        private LlmPromptBuilder $promptBuilder,
    ) {
    }

    /**
     * Classify user intent and extract parameters using LLM.
     *
     * @return array{intent: string, parameters: array}|null
     */
    public function classify(string $message): ?array
    {
        $systemPrompt = $this->promptBuilder->buildSystemPrompt();
        $userMessage = $this->promptBuilder->buildUserMessage($message);

        try {
            $response = $this->aiClient->suggest($systemPrompt, $userMessage);

            return $this->promptBuilder->parseLlmResponse($response);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get all supported intents.
     *
     * @return string[]
     */
    public function getSupportedIntents(): array
    {
        return self::INTENTS;
    }
}
