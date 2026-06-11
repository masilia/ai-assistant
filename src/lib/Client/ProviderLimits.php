<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Per-provider request-shape limits. The adapter says 'I am X
 * provider, here is my chat-completions shape' — this value object
 * says 'X provider has quirks: temperature must be >= 0.01, default
 * test model is Y'. Co-locating the limits with the adapter keeps
 * the model and its quirks in one mental unit.
 */
final readonly class ProviderLimits
{
    public function __construct(
        public float   $minTemperature = 0.0,
        public float   $maxTemperature = 2.0,
        public ?int    $defaultMaxTokens = null,
        public ?string $defaultTestModel = null,
    )
    {
    }

    /**
     * Standard limits for OpenAI-compatible providers.
     * Temperature: 0..2. No default test model — adapters that extend
     * AbstractOpenAiAdapter override getDefaultTestModel() anyway.
     */
    public static function openAiCompatible(): self
    {
        return new self();
    }

    /**
     * Limits for the Anthropic Messages API and MiniMax (which
     * speaks the same protocol). Temperature: 0.01..1 (Anthropic
     * rejects 0 with a 400). Default test model: claude-sonnet-4-5.
     */
    public static function anthropicMessages(string $defaultTestModel): self
    {
        return new self(
            minTemperature: 0.01,
            maxTemperature: 1.0,
            defaultMaxTokens: 4096,
            defaultTestModel: $defaultTestModel,
        );
    }

    /**
     * Clamps a temperature to this provider's [min, max] range.
     * Some providers (Anthropic, MiniMax) reject temperature=0;
     * we clamp to the smallest legal value to keep the request
     * valid without silently ignoring the editor's choice.
     */
    public function clampTemperature(float $temperature): float
    {
        if ($temperature < $this->minTemperature) {
            return $this->minTemperature;
        }
        if ($temperature > $this->maxTemperature) {
            return $this->maxTemperature;
        }
        return $temperature;
    }
}
