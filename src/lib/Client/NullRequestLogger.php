<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Default no-op RequestLogger. Used when no real logger is registered
 * (e.g. unit tests, or a host app that wants to opt out of telemetry).
 */
final class NullRequestLogger implements RequestLoggerInterface
{
    public function log(array $record): void
    {
        // intentionally empty
    }
}
