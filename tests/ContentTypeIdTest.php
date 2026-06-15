<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests;

use Masilia\AiAssistant\ContentTypeId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ContentTypeId value object constants.
 *
 * These tests ensure the canonical content type identifiers remain stable
 * and match the bundle Configuration defaults.
 */
final class ContentTypeIdTest extends TestCase
{
    public function testSiteConstant(): void
    {
        self::assertSame('site', ContentTypeId::SITE);
    }

    public function testHomePageConstant(): void
    {
        self::assertSame('home_page', ContentTypeId::HOME_PAGE);
    }

    public function testPageConstant(): void
    {
        self::assertSame('page', ContentTypeId::PAGE);
    }

    public function testLayoutConstant(): void
    {
        self::assertSame('layout_config', ContentTypeId::LAYOUT);
    }

    public function testFolderConstant(): void
    {
        self::assertSame('folder', ContentTypeId::FOLDER);
    }

    public function testAllConstantsAreNonEmptyStrings(): void
    {
        $constants = [
            ContentTypeId::SITE,
            ContentTypeId::HOME_PAGE,
            ContentTypeId::PAGE,
            ContentTypeId::LAYOUT,
            ContentTypeId::FOLDER,
        ];

        foreach ($constants as $constant) {
            self::assertIsString($constant);
            self::assertNotEmpty($constant);
        }
    }

    public function testAllConstantsAreUnique(): void
    {
        $constants = [
            ContentTypeId::SITE,
            ContentTypeId::HOME_PAGE,
            ContentTypeId::PAGE,
            ContentTypeId::LAYOUT,
            ContentTypeId::FOLDER,
        ];

        self::assertSame(count($constants), count(array_unique($constants)));
    }
}
