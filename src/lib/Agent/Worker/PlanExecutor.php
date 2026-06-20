<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

use Closure;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ContentCreator;
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
    /**
     * Closure signature: fn(string $contentType, int[] $parentLocationIds, array $fields, string $languageCode): array{content: Content, location: ?Location}
     *
     * @var Closure(string, array<int>, array<string, mixed>, string): array<string, mixed>|null
     */
    private ?Closure $contentFactory;

    public function __construct(
        private ToolRegistry  $toolRegistry,
        private LoggerInterface $aiLogger,
        private ?Repository $repository = null,
        private ?ContentTypeService $contentTypeService = null,
        ?ContentCreator $contentCreator = null,
        ?Closure $contentFactory = null,
    ) {
        $this->contentFactory = $contentFactory
            ?? ($contentCreator !== null
                ? static fn (string $type, array $parents, array $fields, string $lang): array => $contentCreator->createAndPublish($type, $parents, $fields, $lang)
                : null);
    }

    public function execute(Plan $plan): ExecutionResult
    {
        try {
            return match ($plan->intent) {
                Plan::INTENT_CREATE_CONTENT => $this->createContent($plan),
                Plan::INTENT_CREATE_ITEMS => $this->createItems($plan),
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

    private function createItems(Plan $plan): ExecutionResult
    {
        if ($this->repository === null || $this->contentTypeService === null || $this->contentFactory === null) {
            return ExecutionResult::fail('create_items executor dependencies not configured', 'EXECUTOR_NOT_CONFIGURED');
        }

        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        $contentInfo = $contentService->loadContentInfo($plan->contentId);
        $parentLocation = $contentInfo->getMainLocation();

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

        foreach ($plan->items as $item) {
            $itemType = (string) ($item['type'] ?? '');
            $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];

            if ($itemType === '') {
                $this->aiLogger->warning('[PlanExecutor] Skipping item with no type');
                continue;
            }

            try {
                $result = ($this->contentFactory)(
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
                $itemIds[] = (int) $result['content']->id;
            } catch (Throwable $e) {
                $this->aiLogger->warning('[PlanExecutor] Failed to create item {type}: {msg}', [
                    'type' => $itemType,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return ExecutionResult::ok(
            sprintf('Created %d items under content %d', count($itemIds), $plan->contentId),
            null,
            null,
            ['item_ids' => $itemIds],
        );
    }

    private function findOrCreateFolder(int $parentLocationId, string $name): ?int
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        try {
            $parentLocation = $locationService->loadLocation($parentLocationId);
            $children = $locationService->loadLocationChildren($parentLocation, 0, 100);

            foreach ($children as $child) {
                if ($child->contentInfo->contentTypeId !== $this->folderTypeId()) {
                    continue;
                }
                $childContent = $contentService->loadContent($child->contentInfo->id);
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

            return (int) $published->contentInfo->getMainLocation()->id;
        } catch (Throwable $e) {
            $this->aiLogger->warning('[PlanExecutor] Failed to create folder {name}: {msg}', [
                'name' => $name,
                'msg' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function folderTypeId(): int
    {
        return $this->contentTypeService
            ->loadContentTypeByIdentifier(ContentTypeId::FOLDER)
            ->id;
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
            $contentId !== null ? (int) $contentId : null,
            $locationId !== null ? (int) $locationId : null,
            $data,
        );
    }
}
