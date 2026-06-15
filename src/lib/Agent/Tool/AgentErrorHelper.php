<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Psr\Log\LoggerInterface;

final class AgentErrorHelper
{
    public static function logAndReturn(
        LoggerInterface $logger,
        \Throwable $e,
        string $action,
    ): ToolResult {
        $logger->error('[Agent] {action}: {message}', [
            'action' => $action,
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);

        return ToolResult::error(self::userMessage($e, $action));
    }

    public static function userMessage(\Throwable $e, string $action): string
    {
        $class = substr($e::class, strrpos($e::class, '\\') + 1) ?: $e::class;

        return sprintf('Failed to %s: %s', $action, $class);
    }

    public static function unauthorized(string $action): ToolResult
    {
        return ToolResult::error(sprintf('Permission denied: cannot %s', $action));
    }

    public static function handle(
        LoggerInterface $logger,
        \Throwable $e,
        string $action,
    ): ToolResult {
        if ($e instanceof UnauthorizedException) {
            return self::unauthorized($action);
        }

        return self::logAndReturn($logger, $e, $action);
    }
}
