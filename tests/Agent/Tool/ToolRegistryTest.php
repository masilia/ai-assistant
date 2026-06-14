<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool;

use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    public function testRegisterAndGetTool(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('test_tool');

        $registry = new ToolRegistry();
        $registry = $registry->register($tool);

        self::assertSame($tool, $registry->get('test_tool'));
    }

    public function testGetReturnsNullForUnknownTool(): void
    {
        $registry = new ToolRegistry();

        self::assertNull($registry->get('nonexistent'));
    }

    public function testGetAllReturnsAllTools(): void
    {
        $tool1 = $this->createMock(ToolInterface::class);
        $tool1->method('getName')->willReturn('tool1');

        $tool2 = $this->createMock(ToolInterface::class);
        $tool2->method('getName')->willReturn('tool2');

        $registry = new ToolRegistry([$tool1, $tool2]);

        $all = $registry->getAll();
        self::assertCount(2, $all);
        self::assertArrayHasKey('tool1', $all);
        self::assertArrayHasKey('tool2', $all);
    }

    public function testGetSchemasReturnsAllSchemas(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('test_tool');
        $tool->method('getDescription')->willReturn('A test tool');
        $tool->method('getParameters')->willReturn(['type' => 'object']);

        $registry = new ToolRegistry([$tool]);

        $schemas = $registry->getSchemas();
        self::assertCount(1, $schemas);
        self::assertSame('test_tool', $schemas[0]['name']);
        self::assertSame('A test tool', $schemas[0]['description']);
    }

    public function testConstructorWithDuplicateNamesLastWins(): void
    {
        $tool1 = $this->createMock(ToolInterface::class);
        $tool1->method('getName')->willReturn('same_name');

        $tool2 = $this->createMock(ToolInterface::class);
        $tool2->method('getName')->willReturn('same_name');

        $registry = new ToolRegistry([$tool1, $tool2]);

        self::assertSame($tool2, $registry->get('same_name'));
    }
}
