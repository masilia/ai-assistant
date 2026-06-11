<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Shared helper for adapters that need to extract the host (scheme + authority)
 * from a configured endpoint URL and append an operation-specific path.
 */
trait EndpointUrlHelperTrait
{
    /**
     * Extract the origin (scheme://host[:port]) from a URL, discarding any path.
     */
    private static function extractHost(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid URL: %s', $url));
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }
}
