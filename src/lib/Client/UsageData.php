<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Type-safe representation of LLM token usage data.
 *
 * Replaces the untyped `?array` that was previously passed through
 * StreamEvent::$usage and RequestLoggerInterface::log().
 */
final readonly class UsageData
{
    public function __construct(
        public int $tokensIn,
        public int $tokensOut,
        public ?string $finishReason = null,
    ) {
    }

    /**
     * Build from the raw array returned by adapter extractUsage().
     *
     * @param array{input?: int, output?: int, finishReason?: string} $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            tokensIn: $raw['input'] ?? 0,
            tokensOut: $raw['output'] ?? 0,
            finishReason: $raw['finishReason'] ?? null,
        );
    }

    /**
     * @return array{input: int, output: int, finishReason: ?string}
     */
    public function toArray(): array
    {
        return [
            'input' => $this->tokensIn,
            'output' => $this->tokensOut,
            'finishReason' => $this->finishReason,
        ];
    }
}
