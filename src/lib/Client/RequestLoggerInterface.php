<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Records one AI API call. Implemented in the bundle layer (writing to
 * the app_ai_request_log table); the lib depends only on this contract.
 */
interface RequestLoggerInterface
{
    /**
     * @param array{
     *   providerIdentifier: string,
     *   modelIdentifier: string,
     *   success: bool,
     *   latencyMs: int,
     *   errorCode: ?string,
     *   tokensIn: ?int,
     *   tokensOut: ?int,
     *   siteaccess: ?string,
     * } $record
     */
    public function log(array $record): void;
}
