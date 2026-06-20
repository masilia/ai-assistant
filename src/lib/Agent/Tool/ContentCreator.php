<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;

/**
 * Create + publish content items, with field-value transformation.
 *
 * Extracted from the previous {@see ContentPublishHelper} monolith to keep
 * creation responsibilities separate from update responsibilities.
 */
final readonly class ContentCreator
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
    ) {
    }

    public function createAndPublish(
        string $contentTypeIdentifier,
        array $parentLocationIds,
        array $attributes,
        string $languageCode,
        ?string $remoteId = null,
        ?string $locationRemoteId = null,
        ?string $skipFieldId = null,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        $createStruct = $contentService->newContentCreateStruct($contentType, $languageCode);
        $createStruct->remoteId = $remoteId;

        $this->applyFields($createStruct, $contentType, $attributes, $languageCode, $skipFieldId);

        $locStructs = [];
        foreach ($parentLocationIds as $parentId) {
            $locStruct = $locationService->newLocationCreateStruct($parentId);
            if ($locationRemoteId !== null) {
                $locStruct->remoteId = $locationRemoteId . '-' . $parentId;
            }
            $locStructs[] = $locStruct;
        }

        $draft = $contentService->createContent($createStruct, $locStructs);
        $published = $contentService->publishVersion($draft->versionInfo);


        return [
            'content' => $published,
            'location' => $published->contentInfo->getMainLocation(),
        ];
    }

    /**
     * Set fields on a create struct, transforming values via the registry.
     *
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

            $transformedValue = $this->transformerRegistry->transform(
                $fieldDef,
                $fields[$fieldDef->identifier],
            );
            $createStruct->setField($fieldDef->identifier, $transformedValue, $languageCode);
        }
    }
}
