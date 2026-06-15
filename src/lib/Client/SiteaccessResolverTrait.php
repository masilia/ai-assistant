<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\AiConstants;

/**
 * Shared trait for resolving the current siteaccess name.
 */
trait SiteaccessResolverTrait
{
    private function getCurrentSiteaccess(): string
    {
        return $this->siteAccessService->getCurrent()?->name ?? AiConstants::DEFAULT_SITEACCESS;
    }
}
