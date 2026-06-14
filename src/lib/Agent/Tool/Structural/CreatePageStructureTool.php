<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Psr\Log\LoggerInterface;

readonly class CreatePageStructureTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
        private ImageGenerationClient $imageClient,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'create_page_structure';
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
            $contentService = $this->repository->getContentService();
            $locationService = $this->repository->getLocationService();
            $contentTypeService = $this->repository->getContentTypeService();

            $languageCode = $params['language'] ?? 'eng-GB';
            $parentLocationId = (int) $params['parent_location_id'];
            $createdBlocks = [];
            $blockContentIds = [];

            // 0. Pre-generate images for ezimage fields
            $tempFiles = $this->preGenerateImages(
                $params['blocks'],
                $params['title'],
                $contentTypeService,
            );

            // 1. Create page with inline location
            $pageType = $contentTypeService->loadContentTypeByIdentifier('page');
            $pageCreateStruct = $contentService->newContentCreateStruct($pageType, $languageCode);
            $pageCreateStruct->setField('title', $params['title'], $languageCode);
            $pageCreateStruct->setField('description', $params['description'] ?? '', $languageCode);

            $pageLocStruct = $locationService->newLocationCreateStruct($parentLocationId);
            $pageDraft = $contentService->createContent($pageCreateStruct, [$pageLocStruct]);
            $pagePublished = $contentService->publishVersion($pageDraft->versionInfo);
            $pageLocation = $locationService->loadLocation($pagePublished->contentInfo->mainLocationId);

            // 2. Create blocks folder under page
            $folderType = $contentTypeService->loadContentTypeByIdentifier('folder');
            $folderCreateStruct = $contentService->newContentCreateStruct($folderType, $languageCode);
            $folderCreateStruct->setField('name', $params['title'] . ' blocks', $languageCode);

            $folderLocStruct = $locationService->newLocationCreateStruct($pageLocation->id);
            $folderDraft = $contentService->createContent($folderCreateStruct, [$folderLocStruct]);
            $folderPublished = $contentService->publishVersion($folderDraft->versionInfo);
            $folderLocation = $locationService->loadLocation($folderPublished->contentInfo->mainLocationId);

            // 3. Create blocks
            foreach ($params['blocks'] as $blockData) {
                $blockTypeId = $blockData['type'] ?? '';
                $blockFields = $blockData['fields'] ?? [];

                $blockType = $contentTypeService->loadContentTypeByIdentifier($blockTypeId);
                $blockCreateStruct = $contentService->newContentCreateStruct($blockType, $languageCode);

                // Find the relation field for this block type
                $relationFieldDef = $this->findRelationField($blockType);
                $relationFieldId = $relationFieldDef?->identifier;

                // Set fields on the block, skipping the relation field (handled separately)
                foreach ($blockFields as $fieldId => $value) {
                    if ($fieldId === $relationFieldId) {
                        continue; // relation field handled after items are created
                    }
                    if ($blockType->hasFieldDefinition($fieldId)) {
                        $fieldDef = $blockType->getFieldDefinition($fieldId);
                        $fieldType = $fieldDef?->fieldTypeIdentifier ?? '';

                        // ezselection: map label strings to option indices
                        if ($fieldType === 'ezselection' && is_string($value)) {
                            $options = $fieldDef->getFieldSettings()['options'] ?? [];
                            $labelToIndex = array_flip($options);
                            $index = $labelToIndex[$value] ?? null;
                            if ($index !== null) {
                                $value = [$index];
                            }
                        }

                        $transformedValue = $this->transformerRegistry->transform($fieldType, $fieldId, $value);
                        $blockCreateStruct->setField($fieldId, $transformedValue, $languageCode);
                    }
                }

                // Initialize empty relation list
                if ($relationFieldId !== null) {
                    $blockCreateStruct->setField($relationFieldId, [], $languageCode);
                }

                $blockLocStruct = $locationService->newLocationCreateStruct($folderLocation->id);
                $blockDraft = $contentService->createContent($blockCreateStruct, [$blockLocStruct]);
                $blockPublished = $contentService->publishVersion($blockDraft->versionInfo);
                $blockContentIds[] = $blockPublished->id;
                $blockLocation = $locationService->loadLocation($blockPublished->contentInfo->mainLocationId);

                // 4. Create block items from LLM data
                $itemContentIds = [];
                if ($relationFieldId !== null && isset($blockFields[$relationFieldId]) && is_array($blockFields[$relationFieldId])) {
                    $allowedTypes = $this->getAllowedTypes($relationFieldDef);
                    $itemsData = $blockFields[$relationFieldId];

                    foreach ($itemsData as $itemData) {
                        $itemTypeId = $itemData['type'] ?? '';
                        if ($itemTypeId === '' || !in_array($itemTypeId, $allowedTypes, true)) {
                            continue; // skip invalid or disallowed item types
                        }

                        $itemType = $contentTypeService->loadContentTypeByIdentifier($itemTypeId);
                        $itemCreateStruct = $contentService->newContentCreateStruct($itemType, $languageCode);

                        // Set item fields from LLM data with transformation
                        foreach ($itemData as $itemFieldId => $itemValue) {
                            if ($itemFieldId === 'type') {
                                continue; // skip the type key
                            }
                            if ($itemType->hasFieldDefinition($itemFieldId)) {
                                $itemFieldDef = $itemType->getFieldDefinition($itemFieldId);
                                $itemFieldType = $itemFieldDef?->fieldTypeIdentifier ?? '';

                                // ezselection: map label strings to option indices
                                if ($itemFieldType === 'ezselection' && is_string($itemValue)) {
                                    $options = $itemFieldDef->getFieldSettings()['options'] ?? [];
                                    $labelToIndex = array_flip($options);
                                    $index = $labelToIndex[$itemValue] ?? null;
                                    if ($index !== null) {
                                        $itemValue = [$index];
                                    }
                                }

                                $transformedItemValue = $this->transformerRegistry->transform($itemFieldType, $itemFieldId, $itemValue);
                                $itemCreateStruct->setField($itemFieldId, $transformedItemValue, $languageCode);
                            }
                        }

                        $itemLocStruct = $locationService->newLocationCreateStruct($blockLocation->id);
                        $itemDraft = $contentService->createContent($itemCreateStruct, [$itemLocStruct]);
                        $itemPublished = $contentService->publishVersion($itemDraft->versionInfo);
                        $itemContentIds[] = $itemPublished->id;
                    }

                    // Update block's relation list with item IDs
                    if (!empty($itemContentIds)) {
                        $blockDraft2 = $contentService->createContentDraft($blockPublished->contentInfo);
                        $updateStruct = $contentService->newContentUpdateStruct();
                        $updateStruct->setField($relationFieldId, $itemContentIds, $languageCode);
                        $contentService->updateContent($blockDraft2->versionInfo, $updateStruct);
                        $contentService->publishVersion($blockDraft2->versionInfo);
                    }
                }

                $createdBlocks[] = [
                    'type' => $blockTypeId,
                    'content_id' => $blockPublished->id,
                    'location_id' => $blockLocation->id,
                    'items' => $itemContentIds,
                ];
            }

            // 5. Update page's blocks relation list
            if (!empty($blockContentIds)) {
                $pageDraft2 = $contentService->createContentDraft($pagePublished->contentInfo);
                $updateStruct = $contentService->newContentUpdateStruct();
                $updateStruct->setField('blocks', $blockContentIds, $languageCode);
                $contentService->updateContent($pageDraft2->versionInfo, $updateStruct);
                $contentService->publishVersion($pageDraft2->versionInfo);
            }

            $result = [
                'page_id' => $pagePublished->id,
                'page_location_id' => $pageLocation->id,
                'folder_id' => $folderPublished->id,
                'folder_location_id' => $folderLocation->id,
                'blocks' => $createdBlocks,
            ];

            return ToolResult::ok(
                sprintf('Created page "%s" with %d blocks', $params['title'], count($result['blocks'])),
                $result,
            );
        } catch (ContentFieldValidationException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create page structure');
        } catch (BadStateException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create page structure');
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('create page structure');
        } catch (NotFoundException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create page structure');
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create page structure');
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
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

    /**
     * Pre-generate images for all ezimage fields in blocks and items.
     *
     * Scans the blocks array for ezimage fields, generates images using the
     * AI provider, saves them to temp files, and replaces the LLM's alt text
     * with the temp file path so setField() accepts it.
     *
     * @return string[] Temp file paths for cleanup
     */
    private function preGenerateImages(
        array &$blocks,
        string $pageTitle,
        ContentTypeService $contentTypeService,
    ): array {
        $tempFiles = [];

        foreach ($blocks as &$blockData) {
            $blockTypeId = $blockData['type'] ?? '';
            $blockFields = &$blockData['fields'] ?? [];
            if ($blockTypeId === '' || empty($blockFields)) {
                continue;
            }

            $blockType = $contentTypeService->loadContentTypeByIdentifier($blockTypeId);

            // Check block-level ezimage fields
            foreach ($blockFields as $fieldId => &$value) {
                if (!is_string($value)) {
                    continue;
                }
                $fieldDef = $blockType->hasFieldDefinition($fieldId)
                    ? $blockType->getFieldDefinition($fieldId)
                    : null;
                if ($fieldDef !== null && $fieldDef->fieldTypeIdentifier === 'ezimage') {
                    $tempPath = $this->generateImageForField($blockTypeId, $pageTitle, $fieldId, $value);
                    if ($tempPath !== null) {
                        $tempFiles[] = $tempPath;
                        $value = $tempPath;
                    }
                }
            }
            unset($value);

            // Check item-level ezimage fields
            $relationFieldDef = $this->findRelationField($blockType);
            $relationFieldId = $relationFieldDef?->identifier;
            if ($relationFieldId !== null && isset($blockFields[$relationFieldId]) && is_array($blockFields[$relationFieldId])) {
                foreach ($blockFields[$relationFieldId] as &$itemData) {
                    $itemTypeId = $itemData['type'] ?? '';
                    if ($itemTypeId === '') {
                        continue;
                    }
                    $itemType = $contentTypeService->loadContentTypeByIdentifier($itemTypeId);

                    foreach ($itemData as $itemFieldId => &$itemValue) {
                        if (!is_string($itemValue)) {
                            continue;
                        }
                        $itemFieldDef = $itemType->hasFieldDefinition($itemFieldId)
                            ? $itemType->getFieldDefinition($itemFieldId)
                            : null;
                        if ($itemFieldDef !== null && $itemFieldDef->fieldTypeIdentifier === 'ezimage') {
                            $tempPath = $this->generateImageForField($itemTypeId, $pageTitle, $itemFieldId, $itemValue);
                            if ($tempPath !== null) {
                                $tempFiles[] = $tempPath;
                                $itemValue = $tempPath;
                            }
                        }
                    }
                    unset($itemValue);
                }
                unset($itemData);
            }
        }
        unset($blockData);

        return $tempFiles;
    }

    /**
     * Generate an image for a single ezimage field.
     */
    private function generateImageForField(
        string $contentTypeIdentifier,
        string $pageTitle,
        string $fieldIdentifier,
        string $altText,
    ): ?string {
        try {
            $prompt = $this->buildImagePrompt($contentTypeIdentifier, $pageTitle, $fieldIdentifier, $altText);
            $imageResult = $this->imageClient->generate($prompt);

            return $this->saveTempFile($imageResult->imageData, $imageResult->mimeType);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(sprintf(
                'Failed to pre-generate image for field "%s" on %s: %s',
                $fieldIdentifier,
                $contentTypeIdentifier,
                $e->getMessage(),
            ));

            return null;
        }
    }

    /**
     * Build a contextual prompt for image generation.
     */
    private function buildImagePrompt(
        string $contentTypeIdentifier,
        string $pageTitle,
        string $fieldIdentifier,
        string $altText,
    ): string {
        return sprintf(
            'Generate an image for a %s block on page "%s", field "%s". Description: %s',
            $contentTypeIdentifier,
            $pageTitle,
            $fieldIdentifier,
            $altText,
        );
    }

    /**
     * Decode base64 image data and save to a temp file.
     */
    private function saveTempFile(string $imageData, string $mimeType): string
    {
        $ext = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = tempnam(sys_get_temp_dir(), 'ai_img_') . '.' . $ext;
        $decoded = base64_decode($imageData, true);

        if ($decoded === false) {
            throw new \RuntimeException('Failed to decode image data');
        }

        if (file_put_contents($path, $decoded) === false) {
            throw new \RuntimeException(sprintf('Failed to write temp file: %s', $path));
        }

        return $path;
    }
}
