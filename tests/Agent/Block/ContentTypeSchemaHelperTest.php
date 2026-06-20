<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\AiAssistant\Agent\Block\ContentCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ContentTypeSchemaHelperTest extends TestCase
{
    use ContentCatalogFactoryTrait;

    private function getSchemaForField(array $fieldConfig): array
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['test_field' => $fieldConfig],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);

        return $schema['fields']['test_field'];
    }

    public function testBaseShapeIncludesTypeRequiredTranslatable(): void
    {
        $info = $this->getSchemaForField(['type' => 'ezstring']);

        self::assertSame('ezstring', $info['type']);
        self::assertFalse($info['required']);
        self::assertFalse($info['translatable']);
    }

    public function testDescriptionIncludedWhenNonEmpty(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezimage',
            'description' => 'Recommended: 1920x600 px',
        ]);

        self::assertSame('Recommended: 1920x600 px', $info['description']);
    }

    public function testDescriptionOmittedWhenEmpty(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezstring',
            'description' => '',
        ]);

        self::assertArrayNotHasKey('description', $info);
    }

    public function testEzstringMinMaxLength(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezstring',
            'validator' => [
                'StringLengthValidator' => ['minStringLength' => 5, 'maxStringLength' => 200],
            ],
        ]);

        self::assertSame(5, $info['minLength']);
        self::assertSame(200, $info['maxLength']);
    }

    public function testEzstringOmitsLengthWhenNoValidator(): void
    {
        $info = $this->getSchemaForField(['type' => 'ezstring']);

        self::assertArrayNotHasKey('minLength', $info);
        self::assertArrayNotHasKey('maxLength', $info);
    }

    public function testEztextMinMaxLength(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'eztext',
            'validator' => [
                'StringLengthValidator' => ['minStringLength' => 10],
            ],
        ]);

        self::assertSame(10, $info['minLength']);
        self::assertArrayNotHasKey('maxLength', $info);
    }

    public function testEzimageMaxFileSizeAndAltText(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezimage',
            'validator' => [
                'FileSizeValidator' => ['maxFileSize' => 10],
                'AlternativeTextValidator' => ['required' => true],
            ],
        ]);

        self::assertSame(10, $info['maxFileSize']);
        self::assertTrue($info['altTextRequired']);
    }

    public function testEzimageOmitsConstraintsWhenNoValidator(): void
    {
        $info = $this->getSchemaForField(['type' => 'ezimage']);

        self::assertArrayNotHasKey('maxFileSize', $info);
        self::assertArrayNotHasKey('altTextRequired', $info);
    }

    public function testEzmatrixMinRowsMaxRows(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezmatrix',
            'settings' => [
                'columns' => [['identifier' => 'title', 'name' => 'Title']],
            ],
            'validator' => [
                'MatrixValueValidator' => ['minimumRowCount' => 1, 'maximumRowCount' => 5],
            ],
        ]);

        self::assertSame(1, $info['minRows']);
        self::assertSame(5, $info['maxRows']);
    }

    public function testEzmatrixOmitsRowsWhenNoValidator(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezmatrix',
            'settings' => [
                'columns' => [['identifier' => 'title', 'name' => 'Title']],
            ],
        ]);

        self::assertArrayHasKey('columns', $info);
        self::assertArrayNotHasKey('minRows', $info);
        self::assertArrayNotHasKey('maxRows', $info);
    }

    public function testRelationListMinItemsMaxItems(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezobjectrelationlist',
            'settings' => ['selectionContentTypes' => ['card_item']],
            'validator' => [
                'RelationValidator' => ['minimumRelationLimit' => 1, 'maximumRelationLimit' => 10],
            ],
        ]);

        self::assertSame(1, $info['minItems']);
        self::assertSame(10, $info['maxItems']);
    }

    public function testRelationListOmitsItemsWhenNoValidator(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezobjectrelationlist',
            'settings' => ['selectionContentTypes' => ['card_item']],
        ]);

        self::assertArrayHasKey('allowedTypes', $info);
        self::assertArrayNotHasKey('minItems', $info);
        self::assertArrayNotHasKey('maxItems', $info);
    }

    public function testTranslatableFlagExposed(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezstring',
            'translatable' => true,
        ]);

        self::assertTrue($info['translatable']);
    }

    public function testEzselectionOptionsUnchanged(): void
    {
        $info = $this->getSchemaForField([
            'type' => 'ezselection',
            'settings' => ['options' => ['Dark', 'Light', 'Auto']],
        ]);

        self::assertSame(['Dark', 'Light', 'Auto'], $info['options']);
        self::assertArrayNotHasKey('minLength', $info);
    }
}
