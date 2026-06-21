<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Ibexa\Core\FieldType\Float\Value as FloatValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\FloatTransformer;
use PHPUnit\Framework\TestCase;

class FloatTransformerTest extends TestCase
{
    private FloatTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new FloatTransformer();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ezfloat', $this->transformer->getFieldTypeIdentifier());
    }

    public function testTransformFloat(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 3.14);
        self::assertInstanceOf(FloatValue::class, $result);
        self::assertSame(3.14, $result->value);
    }

    public function testTransformInt(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 42);
        self::assertInstanceOf(FloatValue::class, $result);
        self::assertSame(42.0, $result->value);
    }

    public function testTransformNumericString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '2.5');
        self::assertInstanceOf(FloatValue::class, $result);
        self::assertSame(2.5, $result->value);
    }

    public function testTransformEmptyString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '');
        self::assertInstanceOf(FloatValue::class, $result);
        self::assertNull($result->value);
    }

    public function testTransformNull(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), null);
        self::assertInstanceOf(FloatValue::class, $result);
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
