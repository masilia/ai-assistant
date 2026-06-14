<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Throwable;

readonly class CreateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
    ) {
    }

    public function getName(): string
    {
        return 'create_content';
    }

    public function getDescription(): string
    {
        return 'Create a new content item in Ibexa. Returns the created content ID and location ID.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier (e.g., "article", "page", "paragraph")',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID where the content will be created',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Field values as key-value pairs',
                ],
                'remote_id' => [
                    'type' => 'string',
                    'description' => 'Optional remote ID for the content',
                ],
                'location_remote_id' => [
                    'type' => 'string',
                    'description' => 'Optional remote ID for the location',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['content_type', 'parent_location_id', 'attributes'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentTypeIdentifier = $params['content_type'];
            $parentLocationId = (int) $params['parent_location_id'];
            $attributes = $params['attributes'] ?? [];
            $languageCode = $params['language'] ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();
            $remoteId = $params['remote_id'] ?? null;
            $locationRemoteId = $params['location_remote_id'] ?? null;

            $result = $this->repository->sudo(function () use (
                $contentTypeIdentifier,
                $parentLocationId,
                $attributes,
                $languageCode,
                $remoteId,
                $locationRemoteId,
            ) {
                $contentService = $this->repository->getContentService();
                $locationService = $this->repository->getLocationService();
                $contentTypeService = $this->repository->getContentTypeService();

                // Load content type
                $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

                // Create content draft with inline location
                $createStruct = $contentService->newContentCreateStruct($contentType, $languageCode);
                $createStruct->contentType = $contentType;
                $createStruct->mainLanguageCode = $languageCode;
                $createStruct->remoteId = $remoteId;

                // Set field values with transformation
                foreach ($attributes as $fieldIdentifier => $value) {
                    $fieldDef = $contentType->getFieldDefinition($fieldIdentifier);
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

                    $transformedValue = $this->transformerRegistry->transform($fieldType, $fieldIdentifier, $value);
                    $createStruct->setField($fieldIdentifier, $transformedValue, $languageCode);
                }

                $locationCreateStruct = $locationService->newLocationCreateStruct($parentLocationId);
                $locationCreateStruct->remoteId = $locationRemoteId;

                $draft = $contentService->createContent($createStruct, [$locationCreateStruct]);

                // Publish
                $published = $contentService->publishVersion($draft->versionInfo);
                $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

                return [
                    'content_id' => $published->id,
                    'location_id' => $location->id,
                    'remote_id' => $published->remoteId,
                ];
            });

            return ToolResult::ok(
                sprintf('Created %s (ID: %d)', $contentTypeIdentifier, $result['content_id']),
                $result,
            );
        } catch (Throwable $e) {
            return ToolResult::error(sprintf('Failed to create content: %s', $e->getMessage()));
        }
    }
}
