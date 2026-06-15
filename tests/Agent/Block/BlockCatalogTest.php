<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;

final class BlockCatalogTest extends TestCase
{
    private function makeCatalog(): BlockCatalog
    {
        return new BlockCatalog(
            $this->createMock(ContentTypeService::class),
            new NullAdapter(),
        );
    }

    public function testGetCapabilitiesReturnsExpectedCapabilities(): void
    {
        $capabilities = $this->makeCatalog()->getCapabilities();

        self::assertArrayHasKey('hero', $capabilities);
        self::assertArrayHasKey('text', $capabilities);
        self::assertArrayHasKey('cards', $capabilities);
        self::assertArrayHasKey('cta', $capabilities);
        self::assertContains('hero_banner', $capabilities['hero']);
        self::assertContains('paragraph', $capabilities['text']);
    }

    public function testFindBlocksByCapabilityReturnsMatchingBlocks(): void
    {
        $blocks = $this->makeCatalog()->findBlocksByCapability('hero');

        self::assertContains('hero_banner', $blocks);
        self::assertContains('hero_carousel', $blocks);
    }

    public function testFindBlocksByCapabilityReturnsEmptyForUnknown(): void
    {
        $blocks = $this->makeCatalog()->findBlocksByCapability('nonexistent');

        self::assertEmpty($blocks);
    }

    public function testResolveCapabilityReturnsCorrectCapability(): void
    {
        $catalog = $this->makeCatalog();
        // The resolveCapability method is private, but we can test it indirectly
        // through findBlocksByCapability which uses the same mapping
        $heroBlocks = $catalog->findBlocksByCapability('hero');
        self::assertContains('hero_banner', $heroBlocks);

        $ctaBlocks = $catalog->findBlocksByCapability('cta');
        self::assertContains('cta', $ctaBlocks);
    }
}
