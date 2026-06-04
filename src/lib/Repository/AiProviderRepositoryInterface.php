<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Repository;

use Masilia\Bundle\AiAssistant\Entity\AiProvider;

/**
 * Contract for retrieving AI providers with siteaccess awareness.
 *
 * Lives in the lib layer so domain services (e.g. the AI client) depend on an
 * abstraction rather than the concrete Doctrine repository in the bundle layer.
 */
interface AiProviderRepositoryInterface
{
    /**
     * Finds the active provider for a given siteaccess.
     *
     * Resolution order:
     *   1. Active provider scoped to this specific siteaccess
     *   2. Active provider scoped globally (siteaccess = null)
     */
    public function findActiveForSiteaccess(string $siteaccess): ?AiProvider;

    /**
     * Finds any active provider (regardless of siteaccess scope).
     */
    public function findActive(): ?AiProvider;
}
