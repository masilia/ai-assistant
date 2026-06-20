<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

interface AiClientInterface
{
    public function suggest(string $systemPrompt, string $userPrompt): string;

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator;

    /**
     * Multi-turn chat with native function-calling / tool-use.
     *
     * @param array  $messages Conversation messages in provider-native format:
     *                         [{role: 'system'|'user'|'assistant'|'tool', content: ...}]
     * @param array  $tools    Tool definitions:
     *                         [{name: string, description: string, parameters: array}]
     *
     * @throws \RuntimeException If the provider cannot be reached or returns an error
     */
    public function chatWithTools(array $messages, array $tools): ToolCallResult;
}
