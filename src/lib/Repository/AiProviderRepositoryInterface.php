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
     * Finds the provider assigned to a given siteaccess that has a chat model configured.
     *
     * Returns null if no provider is assigned to the siteaccess
     * (or none has a chat model configured) for the given siteaccess.
     */
    public function findActiveForSiteaccess(string $siteaccess): ?ResolvedProvider;

    /**
     * Finds any provider with a chat model configured (regardless of siteaccess).
     * Returns null if none. Used by the health checker.
     */
    public function findActive(): ?ResolvedProvider;

    /**
     * Finds the image generation target for a given siteaccess.
     * Returns null if no provider assigned to the siteaccess has an image model configured.
     */
    public function findActiveImageTarget(string $siteaccess): ?ResolvedImageTarget;
}
