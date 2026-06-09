<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\EventListener;

use Masilia\AiAssistant\Client\RequestLoggerInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Flushes the request logger at the end of the HTTP request cycle.
 *
 * The DoctrineRequestLogger batches its flushes to avoid a DB round-trip
 * on every AI call. If the request ends with fewer than 5 calls, the
 * pending rows sit in the EntityManager's unit of work and never reach
 * the database. This listener hooks kernel.terminate (which runs after
 * the response has been sent to the client) and calls flush() to make
 * sure no log rows are lost.
 */
final class RequestLogFlushListener
{
    public function __construct(
        private readonly RequestLoggerInterface $requestLogger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $this->requestLogger->flush();
    }
}
