<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;

/**
 * Update + publish content items, with field-value transformation.
 */
final readonly class ContentUpdater
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
    ) {
    }

    /**
     * Load content, create draft, set fields via transformation, update, publish.
     */
    public function updateFields(int $contentId, array $attributes, string $languageCode): Content
    {
        $contentService = $this->repository->getContentService();
        $content = $contentService->loadContent($contentId);

        return $this->updateByContentInfo($content->contentInfo, $attributes, $languageCode);
    }

    /**
     * Update fields starting from a ContentInfo object (avoids loading full content).
     */
    public function updateByContentInfo(ContentInfo $contentInfo, array $attributes, string $languageCode): Content
    {
        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();

        $draft = $contentService->createContentDraft($contentInfo);
        $contentType = $contentTypeService->loadContentType($contentInfo->contentTypeId);

        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $languageCode;

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if (!array_key_exists($fieldDef->identifier, $attributes)) {
                continue;
            }

            $transformedValue = $this->transformerRegistry->transform(
                $fieldDef,
                $attributes[$fieldDef->identifier],
            );
            $updateStruct->setField($fieldDef->identifier, $transformedValue, $languageCode);
        }

        $contentService->updateContent($draft->versionInfo, $updateStruct);

        return $contentService->publishVersion($draft->versionInfo);
    }
}
