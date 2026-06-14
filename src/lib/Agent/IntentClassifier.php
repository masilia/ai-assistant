<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Client\AiClientInterface;
use Psr\Log\LoggerInterface;

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
        'browse_site_structure',
        'create_site_structure',
    ];

    public function __construct(
        private AiClientInterface $aiClient,
        private LlmPromptBuilder $promptBuilder,
        private LoggerInterface $aiLogger,
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
        } catch (\Throwable $e) {
            $this->aiLogger->warning('[Agent] Intent classification failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

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
