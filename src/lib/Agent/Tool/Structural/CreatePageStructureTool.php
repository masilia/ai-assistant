<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentPublishHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\ContentTypeId;
use Masilia\AiAssistant\FieldId;
use Psr\Log\LoggerInterface;

readonly class CreatePageStructureTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentPublishHelper $publishHelper,
        private ImageGenerationClient $imageClient,
        private LoggerInterface $aiLogger,
        private BlockImagePreGenerator $imagePreGenerator,
    ) {
    }

    public function getName(): string
    {
        return ToolName::CREATE_PAGE_STRUCTURE;
    }

    public function getDescription(): string
    {
        return 'Create a complete page structure: page, blocks folder, block content items, block items, and update the page blocks relation list.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier (default: "' . ContentTypeId::PAGE . '", also supports "' . ContentTypeId::HOME_PAGE . '")',
                    'default' => ContentTypeId::PAGE,
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Page title',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Page description',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID for the page',
                ],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'Array of block definitions. Each block has "type" and "fields". For blocks with child items, include items under the relation field identifier in "fields".',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'fields' => ['type' => 'object'],
                        ],
                    ],
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['title', 'parent_location_id', 'blocks'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        $tempFiles = [];

        try {
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();
            $contentTypeIdentifier = $params['content_type'] ?? ContentTypeId::PAGE;

            // 0. Pre-generate images for ezimage fields (mutates blocks array)
            $tempFiles = $this->imagePreGenerator->preGenerate(
                $params['blocks'],
                $params['title'],
                $this->repository->getContentTypeService(),
            );

            // 1. Create page
            [$pagePublished, $pageLocation] = $this->createPage(
                $contentTypeIdentifier,
                $params['title'],
                $params['description'] ?? '',
                (int) $params['parent_location_id'],
                $languageCode,
            );

            // 2. Create blocks folder under the page
            [$folderPublished, $folderLocation] = $this->createBlocksFolder(
                $params['title'],
                $pageLocation->id,
                $languageCode,
            );

            // 3. Create blocks (and their items) under the folder
            [$createdBlocks, $blockContentIds] = $this->createBlocks(
                $params['blocks'],
                $folderLocation->id,
                $languageCode,
            );

            // 4. Link the page to the created blocks
            $this->linkPageToBlocks($pagePublished->contentInfo, $blockContentIds, $languageCode);

            return ToolResult::ok(
                sprintf('Created page "%s" with %d blocks', $params['title'], count($createdBlocks)),
                [
                    'page_id' => $pagePublished->id,
                    'page_location_id' => $pageLocation->id,
                    'folder_id' => $folderPublished->id,
                    'folder_location_id' => $folderLocation->id,
                    'blocks' => $createdBlocks,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'create page structure');
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Create the page content item with its main location.
     *
     * @return array{0: \Ibexa\Contracts\Core\Repository\Values\Content\Content, 1: \Ibexa\Contracts\Core\Repository\Values\Content\Location}
     */
    private function createPage(
        string $contentTypeIdentifier,
        string $title,
        string $description,
        int $parentLocationId,
        string $languageCode,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $pageType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        $createStruct = $contentService->newContentCreateStruct($pageType, $languageCode);
        $createStruct->setField(FieldId::TITLE, $title, $languageCode);
        $createStruct->setField(FieldId::DESCRIPTION, $description, $languageCode);

        $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
        $draft = $contentService->createContent($createStruct, [$locStruct]);
        $published = $contentService->publishVersion($draft->versionInfo);
        $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

        return [$published, $location];
    }

    /**
     * Create the "<title> blocks" folder under the page.
     *
     * @return array{0: \Ibexa\Contracts\Core\Repository\Values\Content\Content, 1: \Ibexa\Contracts\Core\Repository\Values\Content\Location}
     */
    private function createBlocksFolder(string $pageTitle, int $pageLocationId, string $languageCode): array
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $folderType = $contentTypeService->loadContentTypeByIdentifier(ContentTypeId::FOLDER);
        $createStruct = $contentService->newContentCreateStruct($folderType, $languageCode);
        $createStruct->setField(FieldId::NAME, $pageTitle . ' blocks', $languageCode);

        $locStruct = $locationService->newLocationCreateStruct($pageLocationId);
        $draft = $contentService->createContent($createStruct, [$locStruct]);
        $published = $contentService->publishVersion($draft->versionInfo);
        $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

        return [$published, $location];
    }

    /**
     * Create every block (and its items) under the folder.
     *
     * @return array{0: list<array{type: string, content_id: int, location_id: int, items: int[]}>, 1: int[]}
     */
    private function createBlocks(array $blocksData, int $folderLocationId, string $languageCode): array
    {
        $createdBlocks = [];
        $blockContentIds = [];

        foreach ($blocksData as $blockData) {
            $blockEntry = $this->createBlock($blockData, $folderLocationId, $languageCode);
            if ($blockEntry === null) {
                continue;
            }
            $createdBlocks[] = $blockEntry;
            $blockContentIds[] = $blockEntry['content_id'];
        }

        return [$createdBlocks, $blockContentIds];
    }

    /**
     * Create a single block, its child items, and link them.
     *
     * @return array{type: string, content_id: int, location_id: int, items: int[]}|null
     */
    private function createBlock(array $blockData, int $folderLocationId, string $languageCode): ?array
    {
        $blockTypeId = $blockData['type'] ?? '';
        $blockFields = $blockData['fields'] ?? [];

        if ($blockTypeId === '') {
            return null;
        }

        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $blockType = $contentTypeService->loadContentTypeByIdentifier($blockTypeId);
        $createStruct = $contentService->newContentCreateStruct($blockType, $languageCode);

        $relationFieldDef = $this->findRelationField($blockType);
        $relationFieldId = $relationFieldDef?->identifier;

        // Set non-relation fields with transformation
        $this->publishHelper->applyFields($createStruct, $blockType, $blockFields, $languageCode, $relationFieldId);

        // Initialize empty relation list (filled after items are created)
        if ($relationFieldId !== null) {
            $createStruct->setField($relationFieldId, [], $languageCode);
        }

        $blockLocStruct = $locationService->newLocationCreateStruct($folderLocationId);
        $blockDraft = $contentService->createContent($createStruct, [$blockLocStruct]);
        $blockPublished = $contentService->publishVersion($blockDraft->versionInfo);
        $blockLocation = $locationService->loadLocation($blockPublished->contentInfo->mainLocationId);

        // Create items if the block has a relation field with item data
        $itemContentIds = [];
        if (
            $relationFieldId !== null
            && $relationFieldDef !== null
            && isset($blockFields[$relationFieldId])
            && is_array($blockFields[$relationFieldId])
        ) {
            $itemContentIds = $this->createBlockItems(
                $blockFields[$relationFieldId],
                $relationFieldDef,
                $blockLocation->id,
                $languageCode,
            );

            if (!empty($itemContentIds)) {
                $this->linkBlockToItems(
                    $blockPublished->contentInfo,
                    $relationFieldId,
                    $itemContentIds,
                    $languageCode,
                );
            }
        }

        return [
            'type' => $blockTypeId,
            'content_id' => $blockPublished->id,
            'location_id' => $blockLocation->id,
            'items' => $itemContentIds,
        ];
    }

    /**
     * Create the items belonging to a block.
     *
     * @return int[] Item content IDs
     */
    private function createBlockItems(
        array $itemsData,
        FieldDefinition $relationFieldDef,
        int $blockLocationId,
        string $languageCode,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();
        $allowedTypes = $this->getAllowedTypes($relationFieldDef);

        $itemContentIds = [];

        foreach ($itemsData as $itemData) {
            $itemTypeId = $itemData['type'] ?? '';
            if ($itemTypeId === '' || !in_array($itemTypeId, $allowedTypes, true)) {
                continue;
            }

            $itemType = $contentTypeService->loadContentTypeByIdentifier($itemTypeId);
            $createStruct = $contentService->newContentCreateStruct($itemType, $languageCode);

            // Strip the 'type' key — it's metadata, not a field
            $itemFields = $itemData;
            unset($itemFields['type']);

            $this->publishHelper->applyFields($createStruct, $itemType, $itemFields, $languageCode);

            $locStruct = $locationService->newLocationCreateStruct($blockLocationId);
            $itemDraft = $contentService->createContent($createStruct, [$locStruct]);
            $itemPublished = $contentService->publishVersion($itemDraft->versionInfo);
            $itemContentIds[] = $itemPublished->id;
        }

        return $itemContentIds;
    }

    /**
     * Update a block to point its relation list at the created items.
     */
    private function linkBlockToItems(
        \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo $blockInfo,
        string $relationFieldId,
        array $itemContentIds,
        string $languageCode,
    ): void {
        $contentService = $this->repository->getContentService();

        $draft = $contentService->createContentDraft($blockInfo);
        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->setField($relationFieldId, $itemContentIds, $languageCode);
        $contentService->updateContent($draft->versionInfo, $updateStruct);
        $contentService->publishVersion($draft->versionInfo);
    }

    /**
     * Update the page to point its "blocks" relation list at the created blocks.
     */
    private function linkPageToBlocks(
        \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo $pageInfo,
        array $blockContentIds,
        string $languageCode,
    ): void {
        if (empty($blockContentIds)) {
            return;
        }

        $contentService = $this->repository->getContentService();

        $draft = $contentService->createContentDraft($pageInfo);
        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->setField(FieldId::BLOCKS, $blockContentIds, $languageCode);
        $contentService->updateContent($draft->versionInfo, $updateStruct);
        $contentService->publishVersion($draft->versionInfo);
    }

    /**
     * Find the first ezobjectrelationlist field on a content type.
     */
    private function findRelationField(ContentType $contentType): ?FieldDefinition
    {
        foreach ($contentType->fieldDefinitions as $fieldDef) {
            if ($fieldDef->fieldTypeIdentifier === 'ezobjectrelationlist') {
                return $fieldDef;
            }
        }

        return null;
    }

    /**
     * Get allowed content type identifiers from a relation field definition.
     *
     * @return string[]
     */
    private function getAllowedTypes(FieldDefinition $fieldDef): array
    {
        $settings = $fieldDef->getFieldSettings();

        return $settings['selectionContentTypes'] ?? [];
    }
}
