<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests;

use InvalidArgumentException;
use Masilia\AiAssistant\FieldFormat;
use Masilia\AiAssistant\FieldFormatResolver;
use PHPUnit\Framework\TestCase;

final class FieldFormatResolverTest extends TestCase
{
    private FieldFormatResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FieldFormatResolver();
    }

    /**
     * @dataProvider supportedFieldTypes
     */
    public function testResolveReturnsExpectedFormat(string $fieldType, FieldFormat $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($fieldType));
    }

    /**
     * @return array<string, array{string, FieldFormat}>
     */
    public static function supportedFieldTypes(): array
    {
        return [
            'ezstring' => ['ezstring', FieldFormat::PLAIN_TEXT],
            'eztext' => ['eztext', FieldFormat::TEXT_BLOCK],
            'ezrichtext' => ['ezrichtext', FieldFormat::HTML],
        ];
    }

    public function testSupports(): void
    {
        self::assertTrue($this->resolver->supports('ezstring'));
        self::assertFalse($this->resolver->supports('ezboolean'));
    }

    public function testResolveThrowsOnUnsupportedType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('ezboolean');
    }

    public function testGetSupportedFieldTypesMapsCssClassesToIdentifiers(): void
    {
        $map = $this->resolver->getSupportedFieldTypes();

        self::assertSame('ezstring', $map['ibexa-field-edit--ezstring']);
        self::assertSame('ezrichtext', $map['ibexa-field-edit--ezrichtext']);
    }
}
