<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\Core\Variation\VariationHandler;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\Value;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas;
use Novactive\Bundle\eZSEOBundle\Core\Meta;
use Psr\Log\LoggerInterface;

/**
 * Converts LLM output into novaseometas Value for Ibexa.
 *
 * The LLM outputs a JSON object with meta keys as keys and content as values:
 *   { "title": "Page Title", "description": "A description", "og:image": "search query" }
 *
 * For image keys (og:image, twitter:image), searches the media library via
 * fulltext, loads the matching image, generates a social_network_image variation,
 * and uses the variation URI as the meta content.
 *
 * For text and passthrough keys, creates Meta objects directly.
 */
readonly class NovaSeoMetasTransformer implements FieldValueTransformerInterface
{
    private const IMAGE_KEYS = ['og:image', 'twitter:image'];

    public function __construct(
        private Repository $repository,
        private VariationHandler $variationHandler,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getFieldTypeIdentifier(): string
    {
        return Metas\Type::IDENTIFIER;
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $metas = [];
        foreach ($value as $metaName => $metaContent) {
            if (!is_string($metaName)) {
                continue;
            }

            if (in_array($metaName, self::IMAGE_KEYS, true)) {
                $metaContent = $this->resolveImageMeta(is_string($metaContent) ? $metaContent : '');
            }

            $meta = new Meta();
            $meta->setName($metaName);
            $meta->setContent(is_string($metaContent) ? $metaContent : '');
            $meta->setFieldType('text');
            $metas[] = $meta;
        }

        return new Value($metas);
    }

    /**
     * Search the media library for an image matching the description,
     * generate a social_network_image variation, and return its URI.
     */
    private function resolveImageMeta(string $description): string
    {
        if ($description === '') {
            return '';
        }

        try {
            $query = new Query([
                'filter' => new Criterion\LogicalAnd([
                    new Criterion\ContentTypeIdentifier('image'),
                    new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                    new Criterion\FullText($description),
                ]),
                'limit' => 5,
            ]);

            $searchResult = $this->repository->getSearchService()->findContent($query);

            foreach ($searchResult as $hit) {
                $imageContent = $hit->valueObject;
                $imageField = $imageContent->getFieldValue('image');

                if ($imageField === null || $imageField->uri === null) {
                    continue;
                }

                $field = new Field([
                    'value' => $imageField,
                    'fieldDefIdentifier' => 'image',
                    'languageCode' => $imageContent->mainLanguageCode,
                ]);

                $variation = $this->variationHandler->getVariation(
                    $field,
                    $imageContent->versionInfo,
                    'social_network_image',
                );

                if ($variation->uri !== null && $variation->uri !== '') {
                    return $variation->uri;
                }
            }
        } catch (\Throwable $e) {
            $this->aiLogger->warning(sprintf(
                'Failed to resolve image for novaseometas "%s": %s',
                $description,
                $e->getMessage(),
            ));
        }

        return '';
    }
}
