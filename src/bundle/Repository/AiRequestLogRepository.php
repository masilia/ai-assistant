<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\Bundle\AiAssistant\Entity\AiRequestLog;

/**
 * @extends ServiceEntityRepository<AiRequestLog>
 */
class AiRequestLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiRequestLog::class);
    }

    /**
     * Returns aggregate counts: total requests, success count, error count,
     * average latency (ms), total input + output tokens.
     *
     * @return array{
     *   total: int, success: int, error: int,
     *   avgLatencyMs: int, tokensIn: int, tokensOut: int,
     * }
     */
    public function aggregateSince(\DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) AS total')
            ->addSelect('SUM(CASE WHEN l.success = 1 THEN 1 ELSE 0 END) AS success')
            ->addSelect('SUM(CASE WHEN l.success = 0 THEN 1 ELSE 0 END) AS error')
            ->addSelect('AVG(l.latencyMs) AS avgLatencyMs')
            ->addSelect('COALESCE(SUM(l.tokensIn), 0) AS tokensIn')
            ->addSelect('COALESCE(SUM(l.tokensOut), 0) AS tokensOut')
            ->where('l.createdAt >= :since')
            ->setParameter('since', $since);

        $row = $qb->getQuery()->getSingleResult();

        return [
            'total'        => (int)($row['total'] ?? 0),
            'success'      => (int)($row['success'] ?? 0),
            'error'        => (int)($row['error'] ?? 0),
            'avgLatencyMs' => (int)($row['avgLatencyMs'] ?? 0),
            'tokensIn'     => (int)($row['tokensIn'] ?? 0),
            'tokensOut'    => (int)($row['tokensOut'] ?? 0),
        ];
    }

    /**
     * Returns per-provider totals since the given date, ordered by
     * most-used first.
     *
     * @return list<array{providerIdentifier: string, total: int, success: int, error: int, avgLatencyMs: int}>
     */
    public function perProviderSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.providerIdentifier AS providerIdentifier')
            ->addSelect('COUNT(l.id) AS total')
            ->addSelect('SUM(CASE WHEN l.success = 1 THEN 1 ELSE 0 END) AS success')
            ->addSelect('SUM(CASE WHEN l.success = 0 THEN 1 ELSE 0 END) AS error')
            ->addSelect('AVG(l.latencyMs) AS avgLatencyMs')
            ->where('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('l.providerIdentifier')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $r) => [
                'providerIdentifier' => (string)$r['providerIdentifier'],
                'total' => (int)$r['total'],
                'success' => (int)$r['success'],
                'error' => (int)$r['error'],
                'avgLatencyMs' => (int)$r['avgLatencyMs'],
            ],
            $rows
        );
    }
}
