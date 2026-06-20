<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\AiAssistant\Agent\Block\ContentCatalog;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Concrete subclass of the abstract NotFoundException for testing.
 */
final class TestNotFoundException extends \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
{
}

/**
 * @internal test-only
 */
trait ContentCatalogFactoryTrait
{
    /**
     * Build a real {@see ContentCatalog} from a plain array of content type definitions.
     *
     * Content types are organized by group name:
     *   'Content' => ['page' => [...], 'article' => [...]]
     *
     * @param array<string, array<string, array{name: string, fields: array<string, string|array{type: string, settings?: array<string, mixed>, validator?: array<string, mixed>, required?: bool}>}>> $groups
     */
    private function createContentCatalog(array $groups): ContentCatalog
    {
        $groupMap = [];

        foreach ($groups as $groupIdentifier => $types) {
            $contentTypes = [];
            foreach ($types as $identifier => $type) {
                $fieldDefinitions = [];
                foreach ($type['fields'] as $fieldIdentifier => $fieldConfig) {
                    if (is_string($fieldConfig)) {
                        $fieldConfig = ['type' => $fieldConfig];
                    }

                $fieldDefinitions[] = new FakeFieldDefinition(
                    $fieldIdentifier,
                    $fieldConfig['type'],
                    $fieldConfig['settings'] ?? [],
                    $fieldConfig['validator'] ?? [],
                    $fieldConfig['required'] ?? false,
                    $fieldConfig['description'] ?? '',
                    $fieldConfig['translatable'] ?? false,
                );
                }

                $contentType = new FakeContentType(
                    $identifier,
                    $type['name'],
                    new FieldDefinitionCollection($fieldDefinitions),
                );

                $contentTypes[] = $contentType;
            }

            $group = $this->createStub(ContentTypeGroup::class);
            $groupMap[$groupIdentifier] = ['group' => $group, 'types' => $contentTypes];
        }

        $contentTypeService = $this->createStub(ContentTypeService::class);
        $contentTypeService->method('loadContentTypeGroupByIdentifier')
            ->willReturnCallback(function (string $identifier) use (&$groupMap) {
                if (!isset($groupMap[$identifier])) {
                    throw new TestNotFoundException();
                }
                return $groupMap[$identifier]['group'];
            });
        $contentTypeService->method('loadContentTypes')
            ->willReturnCallback(function (ContentTypeGroup $group) use (&$groupMap) {
                foreach ($groupMap as $entry) {
                    if ($entry['group'] === $group) {
                        return $entry['types'];
                    }
                }
                return [];
            });

        return new ContentCatalog($contentTypeService, new ArrayAdapter());
    }
}
