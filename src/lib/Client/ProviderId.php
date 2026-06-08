<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

final class ProviderId
{
    public const OPENAI    = 'openai';
    public const ANTHROPIC = 'anthropic';
    public const MISTRAL   = 'mistral';
    public const OLLAMA    = 'ollama';
    public const MINIMAX   = 'minimax';
    public const QWEN      = 'qwen';

    public const ALL = [
        self::OPENAI,
        self::ANTHROPIC,
        self::MISTRAL,
        self::OLLAMA,
        self::MINIMAX,
        self::QWEN,
    ];

    public static function isValid(string $identifier): bool
    {
        return in_array($identifier, self::ALL, true);
    }

    public static function displayName(string $identifier): string
    {
        return match ($identifier) {
            self::OPENAI    => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::MISTRAL   => 'Mistral',
            self::OLLAMA    => 'Ollama',
            self::MINIMAX   => 'MiniMax',
            self::QWEN      => 'Qwen',
            default         => ucfirst($identifier),
        };
    }
}
