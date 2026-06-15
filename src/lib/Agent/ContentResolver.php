<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidCriterionArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;

/**
 * Resolves a content_id from a (siteaccess, page_name) pair.
 *
 * Encapsulates the "search by name under siteaccess subtree" pattern used
 * by the orchestrator for update flows where the user identifies a page
 * by name instead of providing the ID directly.
 */
readonly class ContentResolver
{
    public function __construct(
        private Repository                 $repository,
        private SiteaccessLocationResolver $locationResolver,
    )
    {
    }

    /**
     * Find the first content_id matching $pageName under the siteaccess root.
     * Returns null if either resolution fails.
     */
    public function findBySiteaccessAndName(
        string $siteaccess,
        string $pageName,
        string $contentTypeIdentifier = 'page',
    ): ?int
    {
        $rootLocationId = $this->locationResolver->resolve($siteaccess);
        if ($rootLocationId === null) {
            return null;
        }

        try {
            $location = $this->repository->getLocationService()->loadLocation($rootLocationId);
        } catch (NotFoundException|UnauthorizedException $e) {
            return null;
        }


        try {
            return $this->repository->getSearchService()->findSingle(
                new LogicalAnd([
                    new Criterion\Subtree($location->pathString),
                    new Criterion\ContentTypeIdentifier([$contentTypeIdentifier]),
                    new Criterion\ContentName($pageName),
                ])
            )?->id;
        } catch (InvalidCriterionArgumentException|InvalidArgumentException|NotFoundException $e) {
            return null;
        }

    }
}
