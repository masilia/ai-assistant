<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentUpdater;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class TrashContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentUpdater $contentUpdater,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::TRASH_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Move content to trash. Automatically unlinks the content from any relation-list fields on other content items before trashing. Can be restored later with undo_last_operation.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to trash',
                ],
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to trash (optional if content_id provided)',
                ],
            ],
            'required' => ['content_id'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentService = $this->repository->getContentService();
            $locationService = $this->repository->getLocationService();
            $trashService = $this->repository->getTrashService();

            $contentId = (int) $params['content_id'];
            $content = $contentService->loadContent($contentId);

            $locationId = isset($params['location_id'])
                ? (int) $params['location_id']
                : $content->contentInfo->mainLocationId;

            $location = $locationService->loadLocation($locationId);

            // Unlink reverse relations before trashing
            $unlinkedContents = $this->unlinkReverseRelations($contentId);

            $trashService->trash($location);

            $message = sprintf('Trashed content %d', $contentId);
            if (count($unlinkedContents) > 0) {
                $message .= sprintf(' (unlinked from %d content item(s): %s)',
                    count($unlinkedContents),
                    implode(', ', $unlinkedContents),
                );
            }

            return ToolResult::ok($message, [
                'content_id' => $contentId,
                'location_id' => $locationId,
                'trashed' => true,
                'unlinked_from' => $unlinkedContents,
            ]);
        } catch (Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'trash content');
        }
    }

    /**
     * Loads reverse relations for the content being trashed, and for each
     * FIELD-type relation, removes the trashed content ID from the source
     * content's relation-list field. This prevents broken references after
     * trashing.
     *
     * @return array<int, string> List of "content_id:name" strings that were updated
     */
    private function unlinkReverseRelations(int $contentId): array
    {
        $contentService = $this->repository->getContentService();
        $languageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();

        $contentInfo = $contentService->loadContentInfo($contentId);
        $reverseRelations = $contentService->loadReverseRelations($contentInfo);

        if (empty($reverseRelations)) {
            return [];
        }

        // Group by source content ID → field identifier → IDs to remove
        $updates = [];
        foreach ($reverseRelations as $relation) {
            if ($relation->type !== Relation::FIELD) {
                continue;
            }
            $sourceId = $relation->getSourceContentInfo()->id;
            $fieldId = $relation->sourceFieldDefinitionIdentifier;
            $updates[$sourceId][$fieldId][] = $contentId;
        }

        if (empty($updates)) {
            return [];
        }

        $updated = [];
        foreach ($updates as $sourceContentId => $fields) {
            try {
                $sourceContent = $contentService->loadContent($sourceContentId, [$languageCode]);
                $attributes = [];

                foreach ($fields as $fieldIdentifier => $idsToRemove) {
                    $fieldValue = $sourceContent->getFieldValue($fieldIdentifier);

                    // ezobjectrelationlist: destinationContentIds array
                    if (is_object($fieldValue) && property_exists($fieldValue, 'destinationContentIds')) {
                        $currentIds = $fieldValue->destinationContentIds;
                        $filteredIds = array_values(array_diff($currentIds, $idsToRemove));
                        $attributes[$fieldIdentifier] = $filteredIds;
                    }
                    // ezobjectrelation: single destinationContentId
                    elseif (is_object($fieldValue) && property_exists($fieldValue, 'destinationContentId')) {
                        if (in_array($fieldValue->destinationContentId, $idsToRemove, true)) {
                            $attributes[$fieldIdentifier] = null;
                        }
                    }
                }

                if (!empty($attributes)) {
                    $this->contentUpdater->update($sourceContentId, $attributes, $languageCode);
                    $updated[] = sprintf('%d:%s', $sourceContentId, $sourceContent->contentInfo->name ?? 'unnamed');
                }
            } catch (Throwable $e) {
                $this->aiLogger->warning('[TrashContentTool] Failed to unlink reverse relation from content {sourceId}: {message}', [
                    'sourceId' => $sourceContentId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $updated;
    }
}
