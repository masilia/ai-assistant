<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;

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
     * @throws BadStateException
     * @throws ContentFieldValidationException
     * @throws ContentValidationException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function update(int $contentId, array $attributes, string $languageCode): Content
    {
        $contentService = $this->repository->getContentService();

        $content = $contentService->loadContent($contentId);
        $contentType = $content->getContentType();

        $draft = $contentService->createContentDraft($content->contentInfo);

        $updateStruct = $contentService->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $languageCode;

        $fieldsUpdated = 0;
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if (!array_key_exists($fieldDef->identifier, $attributes)) {
                continue;
            }

            $transformedValue = $this->transformerRegistry->transform(
                $fieldDef,
                $attributes[$fieldDef->identifier],
            );
            $updateStruct->setField($fieldDef->identifier, $transformedValue, $languageCode);
            $fieldsUpdated++;
        }

        if ($fieldsUpdated === 0) {
            throw new \InvalidArgumentException(sprintf(
                'No fields matched the provided attributes for content %d. Available fields: %s',
                $contentId,
                implode(', ', array_map(
                    static fn ($f) => $f->identifier,
                    iterator_to_array($contentType->getFieldDefinitions()),
                )),
            ));
        }

        $contentService->updateContent($draft->versionInfo, $updateStruct);

        return $contentService->publishVersion($draft->versionInfo);
    }
}
