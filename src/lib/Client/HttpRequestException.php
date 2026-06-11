<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Thrown when a provider's HTTP response is not OK (status != 200).
 *
 * Carries the status code as a property so callers (the request logger's
 * `errorCode` extractor, the admin dashboard, future retry logic) can
 * read it directly instead of regex-parsing the message string.
 */
final class HttpRequestException extends \RuntimeException
{
    public function __construct(
        string $providerDisplayName,
        public readonly int $statusCode,
        string $body,
    ) {
        parent::__construct(sprintf(
            '%s API error (HTTP %d): %s',
            $providerDisplayName,
            $statusCode,
            $body
        ));
    }
}
