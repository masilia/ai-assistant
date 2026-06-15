<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;

/**
 * Resolves parent location ID from a siteaccess name.
 *
 * Priority:
 * 1. Explicit location ID (if provided)
 * 2. Resolve from siteaccess name via ConfigResolver
 * 3. Current request siteaccess fallback
 */
readonly class SiteaccessLocationResolver
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
    ) {
    }

    /**
     * @param string   $siteaccess         Siteaccess name (empty = use current)
     * @param int|null $explicitLocationId  Explicit location ID override
     */
    public function resolve(string $siteaccess = '', ?int $explicitLocationId = null): ?int
    {
        // 1. Explicit location ID
        if ($explicitLocationId !== null) {
            return $explicitLocationId;
        }

        // 2. Resolve from siteaccess name
        if ($siteaccess !== '') {
            try {
                return (int) $this->configResolver->getParameter(
                    'content.tree_root.location_id',
                    null,
                    $siteaccess,
                );
            } catch (\Throwable) {
                return null;
            }
        }

        // 3. Current request siteaccess
        try {
            return (int) $this->configResolver->getParameter('content.tree_root.location_id');
        } catch (\Throwable) {
            return null;
        }
    }
}
