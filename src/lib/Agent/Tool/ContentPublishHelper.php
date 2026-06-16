<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Psr\Log\LoggerInterface;

/**
 * Shared create+publish and update+publish helpers.
 *
 * Eliminates the near-identical "load type → create struct → set fields →
 * create → publish → load location" and "load content → draft → update
 * struct → set fields → update → publish" sequences that were duplicated
 * across 9+ tool classes.
 */
final class ContentPublishHelper
{
    public function __construct(
        private readonly Repository $repository,
        private readonly FieldValueTransformerRegistry $transformerRegistry,
        private readonly LoggerInterface $aiLogger,
    ) {
    }

    /**
     * Create content, set fields via transformation, publish, and return
     * the published content + its main location.
     *
     * @param array<string, mixed> $attributes  Field values as key-value pairs
     * @param int[]                $parentLocationIds  One or more parent location IDs
     *
     * @return array{content: Content, location: Location}
     */
    public function createAndPublish(
        string $contentTypeIdentifier,
        array $parentLocationIds,
        array $attributes,
        string $languageCode,
        ?string $remoteId = null,
        ?string $locationRemoteId = null,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        $createStruct = $contentService->newContentCreateStruct($contentType, $languageCode);
        $createStruct->remoteId = $remoteId;

        $this->applyFields($createStruct, $contentType, $attributes, $languageCode);

        $locStructs = [];
        foreach ($parentLocationIds as $parentId) {
            $locStruct = $locationService->newLocationCreateStruct($parentId);
            if ($locationRemoteId !== null) {
                $locStruct->remoteId = $locationRemoteId;
            }
            $locStructs[] = $locStruct;
        }

        $draft = $contentService->createContent($createStruct, $locStructs);
        $published = $contentService->publishVersion($draft->versionInfo);
        $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

        return ['content' => $published, 'location' => $location];
    }

    /**
     * Load content, create draft, set fields via transformation, update, publish.
     *
     * @param array<string, mixed> $attributes  Field values as key-value pairs
     *
     * @return Content  The published content
     */
    public function updateFields(
        int $contentId,
        array $attributes,
        string $languageCode,
    ): Content {
        $contentService = $this->repository->getContentService();

        $content = $contentService->loadContent($contentId);
        $draft = $contentService->createContentDraft($content->contentInfo);

        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $languageCode;

        foreach ($content->getContentType()->getFieldDefinitions() as $fieldDef) {
            if (!array_key_exists($fieldDef->identifier, $attributes)) {
                continue;
            }

            $fieldType = $fieldDef->getFieldTypeIdentifier();
            $transformedValue = $this->transformerRegistry->transform(
                $fieldType,
                $fieldDef->identifier,
                $attributes[$fieldDef->identifier],
                $fieldDef,
            );
            $updateStruct->setField($fieldDef->identifier, $transformedValue, $languageCode);
        }

        $contentService->updateContent($draft->versionInfo, $updateStruct);

        return $contentService->publishVersion($draft->versionInfo);
    }

    /**
     * Set fields on a create struct, transforming values via the registry.
     *
     * @param array<string, mixed> $fields
     */
    public function applyFields(
        ContentCreateStruct $createStruct,
        ContentType $contentType,
        array $fields,
        string $languageCode,
        ?string $skipFieldId = null,
    ): void {
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if ($fieldDef->identifier === $skipFieldId) {
                continue;
            }
            if (!array_key_exists($fieldDef->identifier, $fields)) {
                continue;
            }

            $fieldType = $fieldDef->getFieldTypeIdentifier();
            $transformedValue = $this->transformerRegistry->transform(
                $fieldType,
                $fieldDef->identifier,
                $fields[$fieldDef->identifier],
                $fieldDef,
            );
            $createStruct->setField($fieldDef->identifier, $transformedValue, $languageCode);
        }
    }
}
