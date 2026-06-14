<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\Agent\LlmPromptBuilder;
use Masilia\AiAssistant\NovaSeoPromptBuilder;
use PHPUnit\Framework\TestCase;

final class LlmPromptBuilderTest extends TestCase
{
    private LlmPromptBuilder $builder;

    protected function setUp(): void
    {
        $blockCatalog = $this->createMock(BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([
            'hero_banner' => ['identifier' => 'hero_banner', 'name' => 'Hero Banner', 'fields' => ['title' => 'ezstring']],
            'paragraph' => ['identifier' => 'paragraph', 'name' => 'Paragraph', 'fields' => ['rich_text' => 'ezrichtext']],
        ]);
        $blockCatalog->method('getCapabilities')->willReturn([
            'hero' => ['hero_banner'],
            'text' => ['paragraph'],
        ]);

        $novaSeo = $this->createMock(NovaSeoPromptBuilder::class);
        $novaSeo->method('wholeBlockPrompt')->willReturnArgument(0);

        $this->builder = new LlmPromptBuilder($blockCatalog, $novaSeo);
    }

    public function testBuildSystemPromptIncludesBlockTypes(): void
    {
        $prompt = $this->builder->buildSystemPrompt();

        self::assertStringContainsString('hero_banner', $prompt);
        self::assertStringContainsString('paragraph', $prompt);
        self::assertStringContainsString('hero:', $prompt);
        self::assertStringContainsString('text:', $prompt);
    }

    public function testBuildUserMessageReturnsInputUnchanged(): void
    {
        $message = $this->builder->buildUserMessage('Create a page');

        self::assertSame('Create a page', $message);
    }

    public function testParseLlmResponseParsesValidJson(): void
    {
        $response = '{"intent": "create_page", "parameters": {"title": "Test"}}';

        $result = $this->builder->parseLlmResponse($response);

        self::assertNotNull($result);
        self::assertSame('create_page', $result['intent']);
        self::assertSame(['title' => 'Test'], $result['parameters']);
    }

    public function testParseLlmResponseExtractsJsonFromText(): void
    {
        $response = 'Here is the JSON response:
        {"intent": "list_blocks", "parameters": {}}
        That was the response.';

        $result = $this->builder->parseLlmResponse($response);

        self::assertNotNull($result);
        self::assertSame('list_blocks', $result['intent']);
    }

    public function testParseLlmResponseReturnsNullForInvalidJson(): void
    {
        $result = $this->builder->parseLlmResponse('This is not JSON at all');

        self::assertNull($result);
    }

    public function testParseLlmResponseReturnsNullForMissingIntent(): void
    {
        $response = '{"parameters": {"title": "Test"}}';

        $result = $this->builder->parseLlmResponse($response);

        self::assertNull($result);
    }

    public function testParseLlmResponseHandlesEmptyParameters(): void
    {
        $response = '{"intent": "list_blocks"}';

        $result = $this->builder->parseLlmResponse($response);

        self::assertNotNull($result);
        self::assertSame('list_blocks', $result['intent']);
        self::assertSame([], $result['parameters']);
    }

    public function testParseLlmResponseHandlesNestedJsonInText(): void
    {
        $response = 'The system returned this:
        ```json
        {"intent": "search_content", "parameters": {"query": "climate"}}
        ```
        Please confirm.';

        $result = $this->builder->parseLlmResponse($response);

        self::assertNotNull($result);
        self::assertSame('search_content', $result['intent']);
        self::assertSame('climate', $result['parameters']['query']);
    }
}
