<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Ibexa\Core\FieldType\Checkbox\Value as CheckboxValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\BooleanTransformer;
use PHPUnit\Framework\TestCase;

class BooleanTransformerTest extends TestCase
{
    private BooleanTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new BooleanTransformer();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ezboolean', $this->transformer->getFieldTypeIdentifier());
    }

    public function testTransformBoolTrue(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), true);
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertTrue($result->bool);
    }

    public function testTransformBoolFalse(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), false);
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertFalse($result->bool);
    }

    public function testTransformStringTrue(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 'true');
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertTrue($result->bool);
    }

    public function testTransformStringFalse(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 'false');
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertFalse($result->bool);
    }

    public function testTransformIntOne(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 1);
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertTrue($result->bool);
    }

    public function testTransformIntZero(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 0);
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertFalse($result->bool);
    }

    public function testTransformYesString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), 'yes');
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertTrue($result->bool);
    }

    public function testTransformEmptyString(): void
    {
        $result = $this->transformer->transform($this->fieldDef(), '');
        self::assertInstanceOf(CheckboxValue::class, $result);
        self::assertFalse($result->bool);
    }

    private function fieldDef(): \Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition
    {
        return $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition::class);
    }
}
