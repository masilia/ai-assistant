<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use PHPUnit\Framework\TestCase;

final class BlockCatalogTest extends TestCase
{
    public function testGetCapabilitiesReturnsExpectedCapabilities(): void
    {
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $catalog = new BlockCatalog($contentTypeService);

        $capabilities = $catalog->getCapabilities();

        self::assertArrayHasKey('hero', $capabilities);
        self::assertArrayHasKey('text', $capabilities);
        self::assertArrayHasKey('cards', $capabilities);
        self::assertArrayHasKey('cta', $capabilities);
        self::assertContains('hero_banner', $capabilities['hero']);
        self::contains('paragraph', $capabilities['text']);
    }

    public function testFindBlocksByCapabilityReturnsMatchingBlocks(): void
    {
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $catalog = new BlockCatalog($contentTypeService);

        $blocks = $catalog->findBlocksByCapability('hero');

        self::assertContains('hero_banner', $blocks);
        self::assertContains('hero_carousel', $blocks);
    }

    public function testFindBlocksByCapabilityReturnsEmptyForUnknown(): void
    {
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $catalog = new BlockCatalog($contentTypeService);

        $blocks = $catalog->findBlocksByCapability('nonexistent');

        self::assertEmpty($blocks);
    }

    public function testResolveCapabilityReturnsCorrectCapability(): void
    {
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $catalog = new BlockCatalog($contentTypeService);

        // The resolveCapability method is private, but we can test it indirectly
        // through findBlocksByCapability which uses the same mapping
        $heroBlocks = $catalog->findBlocksByCapability('hero');
        self::assertContains('hero_banner', $heroBlocks);

        $ctaBlocks = $catalog->findBlocksByCapability('cta');
        self::assertContains('cta', $ctaBlocks);
    }
}
