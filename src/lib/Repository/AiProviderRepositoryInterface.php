<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Repository;

use Masilia\AiAssistant\Client\Resolved\ResolvedImageTarget;
use Masilia\AiAssistant\Client\Resolved\ResolvedProvider;

/**
 * Contract for retrieving the active AI provider as a framework-agnostic
 * domain object. Lives in the lib layer so domain services (e.g. the AI
 * client) depend on an abstraction rather than the concrete Doctrine
 * repository / entity in the bundle layer.
 */
interface AiProviderRepositoryInterface
{
    /**
     * Finds the active provider for a given siteaccess, plus the active
     * model already merged in (so callers don't have to do a second hop).
     *
     * Resolution order:
     *   1. Active provider scoped to this specific siteaccess
     *   2. Active provider scoped globally (siteaccess = null)
     *
     * Returns null if no active provider (or no active model for it)
     * exists for the given siteaccess.
     */
    public function findActiveForSiteaccess(string $siteaccess): ?ResolvedProvider;

    /**
     * Finds any active provider (regardless of siteaccess scope).
     * Returns null if none. Used by the admin dashboard's GET /api/data.
     */
    public function findActive(): ?ResolvedProvider;

    /**
     * Finds the active image generation target for a given siteaccess.
     * Returns null if no active provider has an image model configured.
     */
    public function findActiveImageTarget(string $siteaccess): ?ResolvedImageTarget;
}
