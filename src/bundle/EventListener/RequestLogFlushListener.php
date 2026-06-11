<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\EventListener;

use Masilia\AiAssistant\Client\RequestLoggerInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Flushes the request logger at the end of the HTTP request cycle.
 *
 * The DoctrineRequestLogger batches its flushes to avoid a DB round-trip
 * on every AI call. If the request ends with fewer than 5 calls, the
 * pending rows sit in the EntityManager's unit of work and never reach
 * the database.
 *
 * Listens on TWO events so log rows are persisted even when the
 * controller throws:
 *   - `kernel.terminate` — the normal path (response already sent)
 *   - `kernel.exception` — fires before the exception bubbles to the
 *     Symfony exception handler, so rows queued mid-request (e.g. a
 *     `RuntimeException` from `AiClient::assertOk`) still reach the DB
 */
final class RequestLogFlushListener
{
    public function __construct(
        private readonly RequestLoggerInterface $requestLogger,
    ) {
    }

    public function __invoke(KernelEvent $event): void
    {
        $this->requestLogger->flush();
    }
}

