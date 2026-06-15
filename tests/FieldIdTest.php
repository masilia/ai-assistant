<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests;

use Masilia\AiAssistant\FieldId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldId value object constants.
 *
 * These tests ensure the canonical field identifiers remain stable
 * across tool implementations.
 */
final class FieldIdTest extends TestCase
{
    public function testTitleConstant(): void
    {
        self::assertSame('title', FieldId::TITLE);
    }

    public function testNameConstant(): void
    {
        self::assertSame('name', FieldId::NAME);
    }

    public function testDescriptionConstant(): void
    {
        self::assertSame('description', FieldId::DESCRIPTION);
    }

    public function testBoTitleConstant(): void
    {
        self::assertSame('bo_title', FieldId::BO_TITLE);
    }

    public function testDomainConstant(): void
    {
        self::assertSame('domain', FieldId::DOMAIN);
    }

    public function testBlocksConstant(): void
    {
        self::assertSame('blocks', FieldId::BLOCKS);
    }

    public function testFaviconConstant(): void
    {
        self::assertSame('favicon', FieldId::FAVICON);
    }

    public function testAllConstantsAreNonEmptyStrings(): void
    {
        $constants = [
            FieldId::TITLE,
            FieldId::NAME,
            FieldId::DESCRIPTION,
            FieldId::BO_TITLE,
            FieldId::DOMAIN,
            FieldId::BLOCKS,
            FieldId::FAVICON,
        ];

        foreach ($constants as $constant) {
            self::assertIsString($constant);
            self::assertNotEmpty($constant);
        }
    }

    public function testAllConstantsAreUnique(): void
    {
        $constants = [
            FieldId::TITLE,
            FieldId::NAME,
            FieldId::DESCRIPTION,
            FieldId::BO_TITLE,
            FieldId::DOMAIN,
            FieldId::BLOCKS,
            FieldId::FAVICON,
        ];

        self::assertSame(count($constants), count(array_unique($constants)));
    }
}
