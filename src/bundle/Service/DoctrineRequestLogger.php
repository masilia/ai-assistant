<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Service;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\AiAssistant\Client\RequestLoggerInterface;
use Masilia\Bundle\AiAssistant\Entity\AiRequestLog;

/**
 * Writes one row to app_ai_request_log per AI call. The lib's AiClient
 * depends on RequestLoggerInterface only; this is the bundle-layer
 * Doctrine implementation.
 *
 * Persistence is deferred: the log row is persisted immediately and
 * flushed only every N calls (configurable) to avoid adding a DB
 * round-trip to every AI request.
 */
class DoctrineRequestLogger implements RequestLoggerInterface
{
    private int $pending = 0;
    private int $flushEvery;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        int $flushEvery = 5,
    ) {
        $this->flushEvery = max(1, $flushEvery);
    }

    public function log(array $record): void
    {
        $row = new AiRequestLog();
        $row->setProviderIdentifier((string)($record['providerIdentifier'] ?? 'unknown'));
        $row->setModelIdentifier((string)($record['modelIdentifier'] ?? 'unknown'));
        $row->setSiteaccess($record['siteaccess'] ?? null);
        $row->setSuccess((bool)($record['success'] ?? false));
        $row->setLatencyMs(max(0, (int)($record['latencyMs'] ?? 0)));
        $row->setErrorCode($record['errorCode'] ?? null);
        $row->setTokensIn(isset($record['tokensIn']) ? (int)$record['tokensIn'] : null);
        $row->setTokensOut(isset($record['tokensOut']) ? (int)$record['tokensOut'] : null);
        $row->setFinishReason($record['finishReason'] ?? null);

        $this->entityManager->persist($row);

        if (++$this->pending >= $this->flushEvery) {
            $this->entityManager->flush();
            $this->pending = 0;
        }
    }

    public function flush(): void
    {
        if ($this->pending > 0) {
            $this->entityManager->flush();
            $this->pending = 0;
        }
    }
}
