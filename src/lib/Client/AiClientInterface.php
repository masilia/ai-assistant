<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

interface AiClientInterface
{
    public function suggest(string $systemPrompt, string $userPrompt): string;

    public function suggestStream(string $systemPrompt, string $userPrompt): \Generator;
}
