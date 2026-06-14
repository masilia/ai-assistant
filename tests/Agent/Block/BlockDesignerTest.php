<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\Block\BlockDesigner;
use Masilia\AiAssistant\Agent\Block\BlockItemCatalog;
use Masilia\AiAssistant\Agent\DTO\BlockDesign;
use PHPUnit\Framework\TestCase;

final class BlockDesignerTest extends TestCase
{
    private BlockDesigner $designer;

    protected function setUp(): void
    {
        $blockCatalog = $this->createMock(BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([
            'hero_banner' => ['identifier' => 'hero_banner', 'name' => 'Hero Banner', 'fields' => ['title' => 'ezstring', 'subtitle' => 'ezstring']],
            'paragraph' => ['identifier' => 'paragraph', 'name' => 'Paragraph', 'fields' => ['rich_text' => 'ezrichtext']],
            'grid_cards' => ['identifier' => 'grid_cards', 'name' => 'Grid Cards', 'fields' => ['title' => 'ezstring']],
            'cta' => ['identifier' => 'cta', 'name' => 'CTA', 'fields' => ['title' => 'ezstring', 'button_text' => 'ezstring']],
        ]);
        $blockCatalog->method('getCapabilities')->willReturn([
            'hero' => ['hero_banner'],
            'text' => ['paragraph'],
            'cards' => ['grid_cards'],
            'cta' => ['cta'],
        ]);
        $blockCatalog->method('getBlockItemTypes')->willReturnMap([
            ['hero_banner', []],
            ['paragraph', []],
            ['grid_cards', ['area_card']],
            ['cta', []],
        ]);
        $blockCatalog->method('findBlocksByCapability')->willReturnMap([
            ['hero', ['hero_banner']],
            ['text', ['paragraph']],
            ['cards', ['grid_cards']],
            ['cta', ['cta']],
        ]);

        $itemCatalog = $this->createMock(BlockItemCatalog::class);

        $this->designer = new BlockDesigner($blockCatalog, $itemCatalog);
    }

    public function testDesignPageStructureCreatesBlocksInOrder(): void
    {
        $pageDesign = $this->designer->designPageStructure([
            'title' => 'Test Page',
            'description' => 'A test page',
            'blocks' => [
                ['type' => 'cta', 'fields' => ['title' => 'Contact']],
                ['type' => 'hero_banner', 'fields' => ['title' => 'Welcome']],
                ['type' => 'paragraph', 'fields' => ['rich_text' => 'Hello']],
            ],
        ]);

        self::assertSame('Test Page', $pageDesign->title);
        self::assertSame('A test page', $pageDesign->description);
        self::assertCount(3, $pageDesign->blocks);

        // Hero first, then text, then CTA
        self::assertSame('hero_banner', $pageDesign->blocks[0]->blockTypeId);
        self::assertSame('paragraph', $pageDesign->blocks[1]->blockTypeId);
        self::assertSame('cta', $pageDesign->blocks[2]->blockTypeId);
    }

    public function testDesignPageStructureHandlesEmptyBlocks(): void
    {
        $pageDesign = $this->designer->designPageStructure([
            'title' => 'Empty Page',
        ]);

        self::assertSame('Empty Page', $pageDesign->title);
        self::assertSame('', $pageDesign->description);
        self::assertCount(0, $pageDesign->blocks);
    }

    public function testDesignPageStructureSkipsBlocksWithoutType(): void
    {
        $pageDesign = $this->designer->designPageStructure([
            'title' => 'Page',
            'blocks' => [
                ['fields' => ['title' => 'No type']],
                ['type' => '', 'fields' => ['title' => 'Empty type']],
                ['type' => 'paragraph', 'fields' => ['rich_text' => 'Valid']],
            ],
        ]);

        self::assertCount(1, $pageDesign->blocks);
        self::assertSame('paragraph', $pageDesign->blocks[0]->blockTypeId);
    }

    public function testOrderBlocksPutsHeroFirst(): void
    {
        $blocks = [
            new BlockDesign(blockTypeId: 'cta', capability: 'cta', fields: [], items: [], position: 0),
            new BlockDesign(blockTypeId: 'hero_banner', capability: 'hero', fields: [], items: [], position: 1),
            new BlockDesign(blockTypeId: 'paragraph', capability: 'text', fields: [], items: [], position: 2),
        ];

        $ordered = $this->designer->orderBlocks($blocks);

        self::assertSame('hero_banner', $ordered[0]->blockTypeId);
        self::assertSame('paragraph', $ordered[1]->blockTypeId);
        self::assertSame('cta', $ordered[2]->blockTypeId);
    }

    public function testOrderBlocksReassignsPositions(): void
    {
        $blocks = [
            new BlockDesign(blockTypeId: 'cta', capability: 'cta', fields: [], items: [], position: 10),
            new BlockDesign(blockTypeId: 'hero_banner', capability: 'hero', fields: [], items: [], position: 20),
        ];

        $ordered = $this->designer->orderBlocks($blocks);

        self::assertSame(0, $ordered[0]->position);
        self::assertSame(1, $ordered[1]->position);
    }

    public function testSelectBlocksReturnsFirstBlockForCapability(): void
    {
        $selected = $this->designer->selectBlocks(['hero', 'cards']);

        self::assertContains('hero_banner', $selected);
        self::assertContains('grid_cards', $selected);
    }

    public function testDesignPageStructureWithItemCount(): void
    {
        $pageDesign = $this->designer->designPageStructure([
            'title' => 'Cards Page',
            'blocks' => [
                ['type' => 'grid_cards', 'item_count' => 4],
            ],
        ]);

        self::assertCount(1, $pageDesign->blocks);
        self::assertCount(1, $pageDesign->blocks[0]->items);
        self::assertSame('area_card', $pageDesign->blocks[0]->items[0]->itemTypeId);
        self::assertSame(4, $pageDesign->blocks[0]->items[0]->count);
    }
}
