<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;

final class BlockCatalogTest extends TestCase
{
    use BlockCatalogFactoryTrait;

    public function testGetAvailableBlocksReturnsDetailedSchemas(): void
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
                    'items' => [
                        'type' => 'ezobjectrelationlist',
                        'settings' => ['selectionContentTypes' => ['card_item']],
                    ],
                    'heading' => 'ezstring',
                ],
            ],
        ]);

        $blocks = $catalog->getAvailableBlocks();

        self::assertArrayHasKey('info_cards', $blocks);
        self::assertSame('Info Cards', $blocks['info_cards']['name']);

        $fields = $blocks['info_cards']['fields'];

        self::assertSame(
            ['type' => 'ezmatrix', 'required' => false, 'columns' => [
                ['identifier' => 'icon', 'name' => 'Icon'],
                ['identifier' => 'title', 'name' => 'Title'],
                ['identifier' => 'body', 'name' => 'Body'],
            ]],
            $fields['cards'],
        );

        self::assertSame(
            ['type' => 'ezobjectrelationlist', 'required' => false, 'allowedTypes' => ['card_item']],
            $fields['items'],
        );

        self::assertSame(['type' => 'ezstring', 'required' => false], $fields['heading']);
    }

    public function testGetBlockSchemaReturnsNullForUnknownBlock(): void
    {
        $catalog = $this->makeCatalog();

        self::assertNull($catalog->getBlockSchema('unknown_block'));
    }

    public function testRenderBlockSummaryIsFlatListWithoutCapabilityHeaders(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => ['title' => 'ezstring'],
            ],
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

        $summary = $catalog->renderBlockSummary();

        self::assertStringStartsWith('Available block types:', $summary);
        self::assertStringNotContainsString('Hero:', $summary);
        self::assertStringNotContainsString('Cards:', $summary);
        self::assertStringNotContainsString('Text:', $summary);
        self::assertStringContainsString('- hero_banner', $summary);
        self::assertStringContainsString('- info_cards', $summary);
    }

    public function testRenderBlockSummaryReturnsEmptyForNoBlocks(): void
    {
        $summary = $this->makeCatalog()->renderBlockSummary();

        self::assertSame('', $summary);
    }

    public function testRenderBlockSummaryIncludesMatrixColumnsInline(): void
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

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('cards:ezmatrix[icon,title]', $summary);
    }

    public function testRenderBlockSummaryIncludesRelationListAllowedTypes(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'items' => [
                        'type' => 'ezobjectrelationlist',
                        'settings' => ['selectionContentTypes' => ['card_item', 'feature']],
                    ],
                ],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('items:ezobjectrelationlist<card_item|feature>', $summary);
    }

    public function testRenderBlockSummaryListsOnlyInstalledBlocks(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => ['title' => 'ezstring'],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('- cta', $summary);
        self::assertStringNotContainsString('- hero_banner', $summary);
        self::assertStringNotContainsString('- paragraph', $summary);
    }

    private function makeCatalog(): BlockCatalog
    {
        return new BlockCatalog(
            $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class),
            new NullAdapter(),
        );
    }
}
