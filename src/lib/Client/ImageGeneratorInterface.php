<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Provider-agnostic image generation capability.
 *
 * Implemented by {@see ImageGenerationClient} in production and by test
 * stubs in unit tests (ImageGenerationClient is `readonly` and cannot
 * be mocked directly).
 */
interface ImageGeneratorInterface
{
    public function isConfigured(): bool;

    /**
     * @throws \Throwable on network / API / configuration failure
     */
    public function generate(
        string $prompt,
        ?string $size = null,
        ?string $quality = null,
    ): ImageGenerationResult;
}
