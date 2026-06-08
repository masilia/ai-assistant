<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Resolved;

/**
 * Immutable, framework-agnostic representation of a fully-resolved AI
 * provider + its active model. Returned by the lib-layer repository
 * interfaces so {@see \\Masilia\\AiAssistant\\Client\\TargetResolver} never
 * depends on Doctrine entities from the bundle layer.
 */
final readonly class ResolvedProvider
{
    public function __construct(
        public string $name,
        public string $providerIdentifier,
        public ?string $apiKey,
        public ?string $apiUrl,
        public string $modelIdentifier,
        public float  $temperature,
        public int    $maxTokens,
    ) {
    }
}
