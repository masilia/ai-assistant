<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\AiAssistant\Repository\AiRequestLogRepository;

/**
 * Read-only API for the AI Usage tab in the admin dashboard.
 * Returns aggregate request counts and per-provider breakdowns
 * since a given offset (24h, 7d, 30d).
 */
#[Route('/admin/ai/usage/api')]
class AiUsageController extends Controller
{
    public function __construct(
        private readonly PermissionResolver       $permissionResolver,
        private readonly AiRequestLogRepository  $logRepository,
    ) {
    }

    #[Route('/data', name: 'app.admin.ai_usage.api.data', methods: ['GET'])]
    public function getData(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->permissionResolver->hasAccess('setup', 'administrate')) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(
                \Masilia\AiAssistant\DTO\AiError::accessDenied()->toArray(),
                \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN
            );
        }

        $now = new \DateTimeImmutable();

        $data = [
            'windows' => [
                '24h' => $this->buildWindow($now, '-24 hours'),
                '7d'  => $this->buildWindow($now, '-7 days'),
                '30d' => $this->buildWindow($now, '-30 days'),
            ],
        ];

        return new \Symfony\Component\HttpFoundation\JsonResponse($data);
    }

    private function buildWindow(\DateTimeImmutable $now, string $interval): array
    {
        $since = $now->modify($interval);
        $totals = $this->logRepository->aggregateSince($since);
        $perProvider = $this->logRepository->perProviderSince($since);

        return [
            'since'       => $since->format(\DateTimeInterface::ATOM),
            'totals'      => $totals,
            'perProvider' => $perProvider,
        ];
    }
}
