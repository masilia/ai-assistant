<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\AiAssistant\Agent\Tool\ImageFileHelper;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Psr\Log\LoggerInterface;

/**
 * Pre-generates images for ezimage fields in blocks and their child items.
 *
 * Scans a blocks array for ezimage fields, generates images using the
 * AI provider, saves them to temp files, and replaces the LLM's alt text
 * with the temp file path so setField() accepts it.
 */
readonly class BlockImagePreGenerator
{
    public function __construct(
        private ImageGenerationClient $imageClient,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * Scan blocks for ezimage fields, generate images, and replace alt text
     * with temp file paths.
     *
     * @param array<int, array{type: string, fields: mixed}> $blocks
     * @return string[] Temp file paths for cleanup
     */
    public function preGenerate(array &$blocks, string $pageTitle, ContentTypeService $contentTypeService): array
    {
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
                    $tempPath = $this->generateForField($blockTypeId, $pageTitle, $fieldId, $value);
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
                            $tempPath = $this->generateForField($itemTypeId, $pageTitle, $itemFieldId, $itemValue);
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

    private function generateForField(
        string $contentTypeIdentifier,
        string $pageTitle,
        string $fieldIdentifier,
        string $altText,
    ): ?string {
        try {
            $prompt = sprintf(
                'Generate an image for a %s block on page "%s", field "%s". Description: %s',
                $contentTypeIdentifier,
                $pageTitle,
                $fieldIdentifier,
                $altText,
            );
            $imageResult = $this->imageClient->generate($prompt);

            return ImageFileHelper::saveTempFile($imageResult->imageData, $imageResult->mimeType);
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

    private function findRelationField(ContentType $contentType): ?\Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition
    {
        foreach ($contentType->fieldDefinitions as $fieldDef) {
            if ($fieldDef->fieldTypeIdentifier === 'ezobjectrelationlist') {
                return $fieldDef;
            }
        }

        return null;
    }
}
