<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Repository;

use Masilia\Bundle\AiAssistant\Entity\AiProvider;

/**
 * Contract for retrieving the active AI provider.
 *
 * Lives in the lib layer so domain services (e.g. the AI client) depend on an
 * abstraction rather than the concrete Doctrine repository in the bundle layer.
 */
interface AiProviderRepositoryInterface
{
    public function findActive(): ?AiProvider;
}
