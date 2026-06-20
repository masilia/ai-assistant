<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Worker;

use Masilia\AiAssistant\Agent\Worker\ExplorationResult;
use Masilia\AiAssistant\Agent\Worker\Plan;
use Masilia\AiAssistant\Agent\Worker\PlanBuilder;
use Masilia\AiAssistant\Tests\Agent\Block\BlockCatalogFactoryTrait;
use Masilia\AiAssistant\Tests\Agent\Block\ContentCatalogFactoryTrait;
use PHPUnit\Framework\TestCase;

final class PlanBuilderTest extends TestCase
{
    use BlockCatalogFactoryTrait;
    use ContentCatalogFactoryTrait;

    public function testBuildCreateContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us', 'subtitle' => 'Our story'],
        ]);

        self::assertSame('create_content', $plan->intent);
        self::assertSame('page', $plan->contentType);
        self::assertSame(42, $plan->parentLocationId);
        self::assertNull($plan->validate());
    }

    public function testBuildUpdateContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'update_content',
            'content_id' => 123,
            'fields' => ['title' => 'New Title'],
        ]);

        self::assertSame(123, $plan->contentId);
        self::assertNull($plan->validate());
    }

    public function testBuildTrashContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'trash_content',
            'content_id' => 456,
        ]);

        self::assertSame(456, $plan->contentId);
        self::assertNull($plan->validate());
    }

    public function testBuildCreateFolderPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_folder',
            'title' => 'Media',
            'parent_location_id' => 1,
        ]);

        self::assertSame('Media', $plan->title);
        self::assertSame(1, $plan->parentLocationId);
        self::assertNull($plan->validate());
    }

    public function testBuildRejectsMissingRequiredFields(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(['intent' => 'create_content']);
    }

    public function testBuildRejectsUnknownIntent(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(['intent' => 'fly_to_mars']);
    }

    public function testBuildWithDefaultsIsAliasForBuild(): void
    {
        $builder = new PlanBuilder();
        $args = [
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
        ];

        $planFromBuild = $builder->build($args);
        $planFromBuildWithDefaults = $builder->buildWithDefaults($args, new ExplorationResult(
            siteaccesses: ['mattcch'],
            matchedSiteaccess: 'mattcch',
            rootLocationId: 42,
            siteStructure: [],
            parentCandidates: [],
            blockTypes: [],
        ));

        self::assertSame($planFromBuild->intent, $planFromBuildWithDefaults->intent);
    }

    public function testBuildWithDefaultsDoesNotInjectHardcodedLayouts(): void
    {
        $builder = new PlanBuilder();

        $plan = $builder->buildWithDefaults([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us'],
        ], new ExplorationResult(
            siteaccesses: ['mattcch'],
            matchedSiteaccess: 'mattcch',
            rootLocationId: 42,
            siteStructure: [],
            parentCandidates: [],
            blockTypes: [
                ['identifier' => 'hero_banner', 'fields' => []],
                ['identifier' => 'paragraph', 'fields' => []],
                ['identifier' => 'grid_cards', 'fields' => []],
                ['identifier' => 'cta', 'fields' => []],
            ],
        ));

        self::assertSame([], $plan->blocks);
    }

    public function testBuildCreateItemsPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_items',
            'content_id' => 200,
            'items' => [
                ['type' => 'card_item', 'fields' => ['icon' => 'star', 'title' => 'Hello']],
                ['type' => 'card_item', 'fields' => ['icon' => 'check', 'title' => 'World']],
            ],
        ]);

        self::assertSame('create_items', $plan->intent);
        self::assertSame(200, $plan->contentId);
        self::assertCount(2, $plan->items);
        self::assertSame('card_item', $plan->items[0]['type']);
        self::assertNull($plan->validate());
    }

    public function testBuildCreateItemsPlanWithLinkField(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_items',
            'content_id' => 200,
            'link_field' => 'blocks',
            'items' => [
                ['type' => 'hero_banner', 'fields' => ['title' => 'Hero']],
            ],
        ]);

        self::assertSame('create_items', $plan->intent);
        self::assertSame('blocks', $plan->linkField);
        self::assertNull($plan->validate());
    }

    public function testPlanLinkFieldRoundTripsThroughToArray(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_items',
            'content_id' => 200,
            'link_field' => 'items',
            'items' => [
                ['type' => 'card_item', 'fields' => ['title' => 'X']],
            ],
        ]);

        $array = $plan->toArray();
        self::assertSame('items', $array['link_field']);

        $restored = Plan::fromArray($array);
        self::assertSame('items', $restored->linkField);
    }

    public function testBuildCreateItemsRejectsMissingContentId(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_items requires content_id');

        $builder->build([
            'intent' => 'create_items',
            'items' => [['type' => 'card_item', 'fields' => []]],
        ]);
    }

    public function testBuildCreateItemsRejectsEmptyItems(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_items requires non-empty items');

        $builder->build([
            'intent' => 'create_items',
            'content_id' => 200,
        ]);
    }

    public function testSchemaValidationAcceptsCorrectMatrixColumns(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ezmatrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                                ['identifier' => 'body', 'name' => 'Body'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'info_cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [
                    ['icon' => 'star', 'title' => 'Hello', 'body' => 'World'],
                    ['icon' => 'check', 'title' => 'Second', 'body' => 'Row'],
                ],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationRejectsUnknownMatrixColumn(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ezmatrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "info_cards": matrix field "cards" row 0 has unknown column "body"');
        $this->expectExceptionMessage('Valid columns: icon, title');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'info_cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [
                    ['icon' => 'star', 'title' => 'Hello', 'body' => 'World'],
                ],
            ],
        ]);
    }

    public function testSchemaValidationSkipsUnknownBlockTypes(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => ['title' => 'ezstring'],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationSkipsNonMatrixFields(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => ['title' => 'ezstring', 'image' => 'ezimage'],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => [
                'title' => 'Hello',
                'image' => ['description' => 'A sunset', 'size' => '1024x1024'],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationSkippedWhenCatalogIsNull(): void
    {
        $builder = new PlanBuilder();

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'any_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationRejectsEmptyString(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ezstring', 'required' => true],
                    'subtitle' => ['type' => 'ezstring'],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "hero_banner": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => '', 'subtitle' => 'A subtitle'],
        ]);
    }

    public function testRequiredFieldValidationRejectsNullValue(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => [
                    'image' => ['type' => 'ezimage', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cta": required field "image" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => ['image' => null],
        ]);
    }

    public function testRequiredFieldValidationRejectsMissingField(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => [
                    'title' => ['type' => 'ezstring', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cta": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => ['subtitle' => 'optional'],
        ]);
    }

    public function testRequiredFieldValidationAcceptsNonEmptyValue(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ezstring', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello World'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationSkipsOptionalEmptyFields(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ezstring', 'required' => true],
                    'subtitle' => ['type' => 'ezstring', 'required' => false],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello', 'subtitle' => ''],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationDetectsEzimageAsAlwaysRequired(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'image' => ['type' => 'ezimage'],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "hero_banner": required field "image" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['image' => ''],
        ]);
    }

    public function testRequiredFieldValidationDetectsMatrixWithMinimumRows(): void
    {
        $catalog = $this->createBlockCatalog([
            'cards' => [
                'name' => 'Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ezmatrix',
                        'required' => true,
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cards": required field "cards" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cards',
            'parent_location_id' => 42,
            'fields' => ['cards' => []],
        ]);
    }

    public function testRequiredFieldValidationAcceptsNonEmptyMatrix(): void
    {
        $catalog = $this->createBlockCatalog([
            'cards' => [
                'name' => 'Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ezmatrix',
                        'required' => true,
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [['icon' => 'star', 'title' => 'Hello']],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationSkippedWhenCatalogIsNull(): void
    {
        $builder = new PlanBuilder();

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'any_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testCreatePageRejectsEmptyContentType(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_content requires contentType');

        $builder->build([
            'intent' => 'create_content',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);
    }

    public function testCreatePageRejectsEmptyFields(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_content requires non-empty fields');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
        ]);
    }

    // --- ContentCatalog: standard content type validation ---

    public function testContentCatalogValidatesRequiredFieldsForPage(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ezstring', 'required' => true],
                        'description' => 'eztext',
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder(contentCatalog: $contentCatalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "page": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['description' => 'Some description'],
        ]);
    }

    public function testContentCatalogAcceptsValidPagePlan(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ezstring', 'required' => true],
                        'description' => 'eztext',
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder(contentCatalog: $contentCatalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us', 'description' => 'Our story'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testContentCatalogFallsBackFromBlockCatalog(): void
    {
        // BlockCatalog doesn't know about 'page', ContentCatalog does
        $blockCatalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => ['title' => 'ezstring'],
            ],
        ]);
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ezstring', 'required' => true],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($blockCatalog, $contentCatalog);

        // page is in ContentCatalog, not BlockCatalog — should still validate
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "page": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['subtitle' => 'optional'],
        ]);
    }

    public function testContentCatalogSkipsUnknownContentTypes(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => ['type' => 'ezstring', 'required' => true]],
                ],
            ],
        ]);
        $builder = new PlanBuilder(contentCatalog: $contentCatalog);

        // 'unknown_type' is not in any catalog — validation is skipped
        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'unknown_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }
}
