<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Controller;

use DateTimeImmutable;
use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\AiAssistant\UsageWindow;
use Masilia\Bundle\AiAssistant\Repository\AiRequestLogRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Read-only API for the AI Usage tab in the admin dashboard.
 * Returns aggregate request counts and per-provider breakdowns
 * since a given offset (24h, 7d, 30d).
 */
#[Route('/admin/ai/usage/api')]
class AiUsageController extends Controller
{
    use RequirePermission;

    public function __construct(
        private readonly PermissionResolver       $permissionResolver,
        private readonly AiRequestLogRepository  $logRepository,
    ) {
    }

    #[Route('/data', name: 'app.admin.ai_usage.api.data', methods: ['GET'])]
    public function getData(): JsonResponse
    {
        if (($denied = $this->requireSetupAdministrate($this->permissionResolver)) !== null) {
            return $denied;
        }

        $now = new DateTimeImmutable();

        $data = [
            'windows' => [
                UsageWindow::Last24Hours->value => $this->buildWindow($now, UsageWindow::Last24Hours),
                UsageWindow::Last7Days->value   => $this->buildWindow($now, UsageWindow::Last7Days),
                UsageWindow::Last30Days->value  => $this->buildWindow($now, UsageWindow::Last30Days),
            ],
        ];

        return new JsonResponse($data);
    }

    private function buildWindow(DateTimeImmutable $now, UsageWindow $window): array
    {
        $since = $now->modify($window->modifier());
        $totals = $this->logRepository->aggregateSince($since);
        $perProvider = $this->logRepository->perProviderSince($since);

        return [
            'since'       => $since->format(\DateTimeInterface::ATOM),
            'totals'      => $totals,
            'perProvider' => $perProvider,
        ];
    }
}
