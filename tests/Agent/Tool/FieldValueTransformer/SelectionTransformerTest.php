<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\SelectionTransformer;
use Masilia\AiAssistant\Tests\Agent\Block\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;

final class SelectionTransformerTest extends TestCase
{
    private SelectionTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new SelectionTransformer();
    }

    public function testGetFieldTypeIdentifier(): void
    {
        self::assertSame('ezselection', $this->transformer->getFieldTypeIdentifier());
    }

    public function testResolvesLabelToIndex(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection', ['options' => ['Dark', 'Light', 'Auto']]);

        $result = $this->transformer->transform($fieldDef, 'Light');

        self::assertSame([1], $result);
    }

    public function testPassesThroughIntegerIndex(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, 0);

        self::assertSame([0], $result);
    }

    public function testPassesThroughArrayIndices(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, [0, 1]);

        self::assertSame([0, 1], $result);
    }

    public function testReturnsEmptyArrayForUnknownLabel(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection', ['options' => ['Dark', 'Light']]);

        $result = $this->transformer->transform($fieldDef, 'NonExistent');

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayForNullValue(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection', ['options' => ['Dark']]);

        $result = $this->transformer->transform($fieldDef, null);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayForEmptyOptions(): void
    {
        $fieldDef = new FakeFieldDefinition('color', 'ezselection');

        $result = $this->transformer->transform($fieldDef, 'Dark');

        self::assertSame([], $result);
    }
}
