<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;

/**
 * Shared trash restoration logic used by both UndoLastTool and
 * RestoreContentTool. Eliminates the near-identical find+recover
 * loop that was duplicated between the two tools.
 */
final class TrashRestorer
{
    public function __construct(
        private readonly Repository $repository,
    ) {
    }

    /**
     * Restore trashed content items by their content IDs.
     *
     * @param int[] $contentIds
     * @return int[] IDs of restored content items
     */
    public function restore(array $contentIds): array
    {
        $trashService = $this->repository->getTrashService();

        $restored = [];
        $trashedItems = $trashService->findTrashItems(new Query([
            'filter' => new Query\Criterion\ContentId($contentIds),
        ]));

        foreach ($trashedItems->items as $trashed) {
            $trashService->recover($trashed);
            $restored[] = $trashed->contentId;
        }

        return $restored;
    }
}
