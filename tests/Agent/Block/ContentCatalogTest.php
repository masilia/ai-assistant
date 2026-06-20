<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use PHPUnit\Framework\TestCase;

final class ContentCatalogTest extends TestCase
{
    use ContentCatalogFactoryTrait;

    public function testGetContentTypeSchemaReturnsFieldDefinitions(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ezstring', 'required' => true],
                        'description' => 'eztext',
                        'blocks' => 'ezmatrix',
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame('page', $schema['identifier']);
        self::assertSame('Page', $schema['name']);
        self::assertArrayHasKey('title', $schema['fields']);
        self::assertArrayHasKey('description', $schema['fields']);
        self::assertArrayHasKey('blocks', $schema['fields']);

        self::assertTrue($schema['fields']['title']['required']);
        self::assertFalse($schema['fields']['description']['required']);
    }

    public function testGetContentTypeSchemaReturnsNullForUnknownType(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ezstring'],
                ],
            ],
        ]);

        self::assertNull($catalog->getContentTypeSchema('unknown_type'));
    }

    public function testLoadsFromMultipleGroups(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => ['type' => 'ezstring', 'required' => true]],
                ],
            ],
            'Media' => [
                'image' => [
                    'name' => 'Image',
                    'fields' => ['image' => ['type' => 'ezimage', 'required' => true]],
                ],
            ],
            'Taxonomies' => [
                'tag' => [
                    'name' => 'Tag',
                    'fields' => ['name' => ['type' => 'ezstring', 'required' => true]],
                ],
            ],
        ]);

        self::assertNotNull($catalog->getContentTypeSchema('page'));
        self::assertNotNull($catalog->getContentTypeSchema('image'));
        self::assertNotNull($catalog->getContentTypeSchema('tag'));
    }

    public function testRequiredFieldDetectionTrustsIsRequired(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ezstring', 'required' => true],
                        'subtitle' => ['type' => 'ezstring', 'required' => false],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['title']['required']);
        self::assertFalse($schema['fields']['subtitle']['required']);
    }

    public function testRequiredFieldDetectionForEzimageAlwaysRequired(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'image' => ['type' => 'ezimage', 'required' => false],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['image']['required']);
    }

    public function testRequiredFieldDetectionForStringLengthValidator(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => [
                            'type' => 'ezstring',
                            'required' => false,
                            'validator' => ['StringLengthValidator' => ['minStringLength' => 1]],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['title']['required']);
    }

    public function testSkipsMissingGroups(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ezstring'],
                ],
            ],
            // Non-existent groups are silently skipped
        ]);

        self::assertNotNull($catalog->getContentTypeSchema('page'));
    }

    public function testGetAvailableContentTypesReturnsAllTypes(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ezstring'],
                ],
                'article' => [
                    'name' => 'Article',
                    'fields' => ['title' => 'ezstring'],
                ],
            ],
        ]);

        $types = $catalog->getAvailableContentTypes();

        self::assertArrayHasKey('page', $types);
        self::assertArrayHasKey('article', $types);
        self::assertCount(2, $types);
    }
}
