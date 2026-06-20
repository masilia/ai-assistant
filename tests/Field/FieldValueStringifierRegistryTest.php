<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;
use Masilia\AiAssistant\Field\FieldValueStringifierRegistry;
use Masilia\AiAssistant\Field\Stringifier\KeywordStringifier;
use Masilia\AiAssistant\Field\Stringifier\SelectionStringifier;
use PHPUnit\Framework\TestCase;

final class FieldValueStringifierRegistryTest extends TestCase
{
    public function testDispatchesToMatchingStringifier(): void
    {
        $registry = new FieldValueStringifierRegistry([
            new SelectionStringifier(),
            new KeywordStringifier(),
        ]);

        self::assertTrue($registry->hasStringifier('ezselection'));
        self::assertTrue($registry->hasStringifier('ezkeyword'));
        self::assertFalse($registry->hasStringifier('ezunknown'));
    }

    public function testReturnsEmptyWhenNoStringifierAndNoFallback(): void
    {
        $registry = new FieldValueStringifierRegistry([]);

        $field = $this->createStub(Field::class);
        $fieldDef = $this->createFieldDefStub('unknown_type');

        self::assertSame('', $registry->toString($field, $fieldDef));
    }

    public function testFallbackIsUsedForUnknownType(): void
    {
        $fallback = new StubFallbackStringifier();
        $registry = new FieldValueStringifierRegistry([$fallback]);

        $field = $this->createStub(Field::class);
        $fieldDef = $this->createFieldDefStub('anything');

        self::assertSame('fallback', $registry->toString($field, $fieldDef));
    }

    public function testSpecificTakesPrecedenceOverFallback(): void
    {
        $fallback = new StubFallbackStringifier();
        $specific = new StubSpecificStringifier();
        $registry = new FieldValueStringifierRegistry([$specific, $fallback]);

        $field = $this->createStub(Field::class);

        // 'stub_type' → specific
        self::assertSame('specific', $registry->toString($field, $this->createFieldDefStub('stub_type')));

        // 'other_type' → fallback
        self::assertSame('fallback', $registry->toString($field, $this->createFieldDefStub('other_type')));
    }

    private function createFieldDefStub(string $typeIdentifier): FieldDefinition
    {
        $stub = $this->createStub(FieldDefinition::class);
        $stub->method('getFieldTypeIdentifier')->willReturn($typeIdentifier);

        return $stub;
    }
}

/**
 * @internal test-only
 */
final class StubFallbackStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return ['_fallback'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        return 'fallback';
    }
}

/**
 * @internal test-only
 */
final class StubSpecificStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return ['stub_type'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        return 'specific';
    }
}
