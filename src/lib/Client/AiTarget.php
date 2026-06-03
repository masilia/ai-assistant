<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;

/**
 * Immutable resolution of "where and how to call the AI provider" — the adapter,
 * endpoint, headers, and model parameters for a single request.
 *
 * @internal
 */
final readonly class AiTarget
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public ProviderAdapterInterface $adapter,
        public string                   $providerIdentifier,
        public string                   $modelIdentifier,
        public float                    $temperature,
        public int                      $maxTokens,
        public string                   $url,
        public array                    $headers,
    ) {
    }
}
