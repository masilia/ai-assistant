<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Ibexa\Core\FieldType\Integer\Value as IntegerValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\IntegerTransformer;
use PHPUnit\Framework\TestCase;

class IntegerTransformerTest extends TestCase
{
    private IntegerTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new IntegerTransformer();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ezinteger', $this->transformer->getFieldTypeIdentifier());
    }

    public function testTransformInt(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 42);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(42, $result->value);
    }

    public function testTransformFloat(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 3.7);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(3, $result->value);
    }

    public function testTransformNumericString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '100');
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame(100, $result->value);
    }

    public function testTransformEmptyString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '');
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertNull($result->value);
    }

    public function testTransformNull(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), null);
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertNull($result->value);
    }

    public function testTransformNonNumericString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 'hello');
        self::assertSame('hello', $result);
    }

    private function fieldDef(): \Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition
    {
        return $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition::class);
    }
}
