<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Resolved;

/**
 * Immutable representation of a resolved image generation target.
 * Parallel to {@see ResolvedProvider} but for image generation.
 */
final readonly class ResolvedImageTarget
{
    public function __construct(
        public string  $providerIdentifier,
        public string  $apiKey,
        public ?string $apiUrl,
        public string  $imageModelIdentifier,
    )
    {
    }
}
