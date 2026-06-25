<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Masilia\AiAssistant\Agent\Orchestrator\WorkerContext;
use Masilia\AiAssistant\Agent\Tool\ContentFactoryInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\ContentTypeId;
use Masilia\AiAssistant\FieldId;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes a Plan by mapping it to the underlying agent tools.
 *
 * Each plan intent is dispatched to exactly one existing tool call.
 * Failures are returned as structured `ExecutionResult` so the orchestrator
 * can surface them to the user without retrying.
 */
final readonly class PlanExecutor
{
    public function __construct(
        private ToolRegistry             $toolRegistry,
        private LoggerInterface          $aiLogger,
        private ?Repository              $repository = null,
        private ?ContentTypeService      $contentTypeService = null,
        private ?ContentFactoryInterface $contentFactory = null,
    )
    {
    }

    public function execute(Plan $plan, ?WorkerContext $context = null): ExecutionResult
    {
        try {
            return match ($plan->intent) {
                Plan::INTENT_CREATE_CONTENT => $this->createContent($plan),
                Plan::INTENT_CREATE_ITEMS => $this->createItems($plan, $context),
                Plan::INTENT_UPDATE_CONTENT => $this->updateContent($plan),
                Plan::INTENT_CREATE_FOLDER => $this->createFolder($plan),
                Plan::INTENT_CREATE_SITE_STRUCTURE => $this->createSiteStructure($plan),
                Plan::INTENT_TRASH_CONTENT => $this->trashContent($plan),
                Plan::INTENT_RESTORE_CONTENT => $this->restoreContent($plan),
                default => ExecutionResult::fail(sprintf('Unknown intent: %s', $plan->intent), 'UNKNOWN_INTENT'),
            };
        } catch (Throwable $e) {
            $this->aiLogger->error('[PlanExecutor] Execution failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return ExecutionResult::fail($e->getMessage(), 'EXECUTION_EXCEPTION');
        }
    }

    private function createContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::CREATE_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('create_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute([
            'content_type' => $plan->contentType,
            'parent_location_id' => $plan->parentLocationId,
            'siteaccess' => $plan->siteaccess,
            'attributes' => $plan->fields,
        ]);

        return $this->toExecutionResult($result, 'content');
    }

    private function toExecutionResult(ToolResult $toolResult, string $entityType): ExecutionResult
    {
        if (!$toolResult->success) {
            return ExecutionResult::fail(
                sprintf('Failed to create %s: %s', $entityType, $toolResult->message),
                'TOOL_FAILED',
            );
        }

        $data = $toolResult->data;
        $contentId = $data['content_id'] ?? $data['page_id'] ?? null;
        $locationId = $data['location_id'] ?? $data['page_location_id'] ?? null;

        return ExecutionResult::ok(
            $toolResult->message !== '' ? $toolResult->message : sprintf('Created %s.', $entityType),
            $contentId !== null ? (int)$contentId : null,
            $locationId !== null ? (int)$locationId : null,
            $data,
        );
    }

    private function createItems(Plan $plan, ?WorkerContext $context = null): ExecutionResult
    {
        if ($this->repository === null || $this->contentTypeService === null || $this->contentFactory === null) {
            return ExecutionResult::fail('create_items executor dependencies not configured', 'EXECUTOR_NOT_CONFIGURED');
        }

        $contentService = $this->repository->getContentService();

        try {
            $contentInfo = $contentService->loadContentInfo($plan->contentId);
        } catch (NotFoundException|UnauthorizedException $e) {
            return ExecutionResult::fail('Content not found', 'CONTENT_NOT_FOUND');
        }
        $parentLocation = $contentInfo->getMainLocation();

        // Validate allowed types for link_field before creating items
        if ($plan->linkField !== null) {
            $typeError = $this->validateAllowedTypes($contentInfo, $plan->linkField, $plan->items);
            if ($typeError !== null) {
                return ExecutionResult::fail($typeError, 'INVALID_RELATION_TARGET');
            }
        }

        $folderName = trim(($contentInfo->name ?? 'items') . ' items') ?: 'items';
        $folderLocationId = $this->findOrCreateFolder(
            $parentLocation->id,
            $folderName
        );

        if ($folderLocationId === null) {
            return ExecutionResult::fail('Could not find or create items folder', 'NO_FOLDER');
        }

        $languageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        $itemIds = [];

        // Emit initial progress for create_items
        $context?->emitEvent([
            'type' => 'step_progress',
            'tool' => 'create_content',
            'message' => sprintf('Creating %d item(s)...', count($plan->items)),
            'progress' => 0,
            'total' => count($plan->items),
        ]);

        foreach ($plan->items as $index => $item) {
            $itemType = (string)($item['type'] ?? '');
            $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];

            if ($itemType === '') {
                $this->aiLogger->warning('[PlanExecutor] Skipping item with no type');
                continue;
            }

            // Emit progress before creating each item
            $context?->emitEvent([
                'type' => 'step_progress',
                'tool' => 'create_content',
                'message' => sprintf('Creating item %d of %d (%s)...', $index + 1, count($plan->items), $itemType),
                'progress' => $index + 1,
                'total' => count($plan->items),
            ]);

            try {
                $result = $this->contentFactory->createAndPublish(
                    $itemType,
                    [$folderLocationId],
                    $fields,
                    $languageCode,
                );
                if (!is_array($result) || !isset($result['content'])) {
                    $this->aiLogger->warning('[PlanExecutor] Content factory returned invalid result for {type}', [
                        'type' => $itemType,
                    ]);
                    continue;
                }
                $itemIds[] = (int)$result['content']->id;
            } catch (Throwable $e) {
                $this->aiLogger->warning('[PlanExecutor] Failed to create item {type}: {msg}', [
                    'type' => $itemType,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if ($plan->linkField !== null && $itemIds !== []) {
            $this->linkItemsToParent($plan->contentId, $plan->linkField, $itemIds, $languageCode);
        }

        return ExecutionResult::ok(
            sprintf('Created %d items under content %d', count($itemIds), $plan->contentId),
            null,
            null,
            ['item_ids' => $itemIds],
        );
    }

    /**
     * Validate that all item types are allowed on the parent's relation list field.
     */
    private function validateAllowedTypes(
        ContentInfo $contentInfo,
        string      $linkField,
        array       $items,
    ): ?string
    {
        $contentType = $contentInfo->getContentType();

        $fieldDef = null;
        foreach ($contentType->getFieldDefinitions() as $def) {
            if ($def->identifier === $linkField) {
                $fieldDef = $def;
                break;
            }
        }

        if ($fieldDef === null) {
            return null;
        }

        $allowedTypes = array_values((array)($fieldDef->fieldSettings['selectionContentTypes'] ?? []));
        if ($allowedTypes === []) {
            return null;
        }

        $invalidTypes = [];
        foreach ($items as $item) {
            $itemType = (string)($item['type'] ?? '');
            if ($itemType !== '' && !in_array($itemType, $allowedTypes, true)) {
                $invalidTypes[] = $itemType;
            }
        }

        if ($invalidTypes !== []) {
            return sprintf(
                'Link field "%s" on content type "%s" allows: [%s]. Got invalid types: [%s]',
                $linkField,
                $contentType->identifier,
                implode(', ', $allowedTypes),
                implode(', ', $invalidTypes),
            );
        }

        return null;
    }

    private function findOrCreateFolder(int $parentLocationId, string $name): ?int
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        try {
            $parentLocation = $locationService->loadLocation($parentLocationId);
            $children = $locationService->loadLocationChildren($parentLocation, 0, 100);

            foreach ($children as $child) {
                if ($child->contentInfo->getContentType()->identifier !== ContentTypeId::FOLDER) {
                    continue;
                }
                $childContent = $child->getContent();
                if (strcasecmp($childContent->getName(), $name) === 0) {
                    return $child->id;
                }
            }
        } catch (Throwable $e) {
            $this->aiLogger->warning('[PlanExecutor] Failed to search for existing folder: {msg}', [
                'msg' => $e->getMessage(),
                'exception' => $e,
            ]);
            return null;
        }

        try {
            $folderType = $this->contentTypeService->loadContentTypeByIdentifier(ContentTypeId::FOLDER);
            $languageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            $createStruct = $contentService->newContentCreateStruct($folderType, $languageCode);
            $createStruct->setField(FieldId::NAME, $name, $languageCode);

            $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
            $draft = $contentService->createContent($createStruct, [$locStruct]);
            $published = $contentService->publishVersion($draft->versionInfo);

            return $published->contentInfo->getMainLocation()->id;
        } catch (Throwable $e) {
            $this->aiLogger->warning('[PlanExecutor] Failed to create folder {name}: {msg}', [
                'name' => $name,
                'msg' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Link created item IDs to the parent content's relation list field.
     *
     * @param list<int> $itemIds
     */
    private function linkItemsToParent(int $contentId, string $linkField, array $itemIds, string $languageCode): void
    {
        $contentService = $this->repository->getContentService();

        try {
            $contentInfo = $contentService->loadContentInfo($contentId);
        } catch (NotFoundException|UnauthorizedException $e) {
            $this->aiLogger->warning('[PlanExecutor] Content {content_id} not found', [
                'content_id' => $contentId,
            ]);
            return;
        }
        $contentType = $contentInfo->getContentType();

        $fieldDef = null;
        foreach ($contentType->getFieldDefinitions() as $def) {
            if ($def->identifier === $linkField) {
                $fieldDef = $def;
                break;
            }
        }

        if ($fieldDef === null) {
            $this->aiLogger->warning('[PlanExecutor] Link field "{field}" not found on content type "{type}"', [
                'field' => $linkField,
                'type' => $contentType->identifier,
            ]);

            return;
        }

        try {
            $draft = $contentService->createContentDraft($contentInfo);
        } catch (UnauthorizedException $e) {
            $this->aiLogger->warning('[PlanExecutor]', [
                'field' => $linkField,
            ]);
            return;
        }
        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $languageCode;
        $updateStruct->setField($linkField, $itemIds, $languageCode);

        try {
            $contentService->updateContent($draft->versionInfo, $updateStruct);
        } catch (BadStateException|ContentFieldValidationException|ContentValidationException|InvalidArgumentException|UnauthorizedException $e) {
            $this->aiLogger->warning('[PlanExecutor] Failed to update content {content_id} via "{field}": {msg}', [
                'content_id' => $contentId,
                'field' => $linkField,
                'msg' => $e->getMessage(),
            ]);
        }
        try {
            $contentService->publishVersion($draft->versionInfo);
        } catch (BadStateException|UnauthorizedException $e) {
            $this->aiLogger->warning('[PlanExecutor] Failed to publish content {content_id} via "{field}": {msg}', [
                'content_id' => $contentId,
                'field' => $linkField,
                'msg' => $e->getMessage(),
            ]);
        }

        $this->aiLogger->info('[PlanExecutor] Linked {count} items to content {id} via "{field}"', [
            'count' => count($itemIds),
            'id' => $contentId,
            'field' => $linkField,
        ]);
    }

    private function updateContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::UPDATE_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('update_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute([
            'content_id' => $plan->contentId,
            'attributes' => $plan->fields,
        ]);

        return $this->toExecutionResult($result, 'content');
    }

    private function createFolder(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::CREATE_FOLDER);
        if ($tool === null) {
            return ExecutionResult::fail('create_folder tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute([
            'name' => $plan->title,
            'parent_location_id' => $plan->parentLocationId,
        ]);

        return $this->toExecutionResult($result, 'folder');
    }

    private function createSiteStructure(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::CREATE_SITE_STRUCTURE);
        if ($tool === null) {
            return ExecutionResult::fail('create_site_structure tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute([
            'site_name' => $plan->title,
            'domain' => $plan->siteaccess ?? '',
            'siteaccess' => $plan->siteaccess,
            'pages' => $plan->blocks,
            'description' => $plan->description,
        ]);

        return $this->toExecutionResult($result, 'site');
    }

    private function trashContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::TRASH_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('trash_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $result = $tool->execute(['content_id' => $plan->contentId]);

        return $this->toExecutionResult($result, 'content');
    }

    private function restoreContent(Plan $plan): ExecutionResult
    {
        $tool = $this->toolRegistry->get(ToolName::RESTORE_CONTENT);
        if ($tool === null) {
            return ExecutionResult::fail('restore_content tool not available', 'TOOL_UNAVAILABLE');
        }
        $ids = $plan->contentId !== null
            ? [$plan->contentId]
            : array_values(array_map('intval', $plan->fields['content_ids'] ?? []));

        $result = $tool->execute(['content_ids' => $ids]);

        return $this->toExecutionResult($result, 'content');
    }
}
