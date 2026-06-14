<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\IntentClassifier;
use Masilia\AiAssistant\Agent\LlmPromptBuilder;
use Masilia\AiAssistant\Client\AiClientInterface;
use PHPUnit\Framework\TestCase;

final class IntentClassifierTest extends TestCase
{
    public function testClassifyReturnsIntentAndParameters(): void
    {
        $llmResponse = '{"intent": "create_page", "parameters": {"title": "Test"}}';

        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('suggest')->willReturn($llmResponse);

        $blockCatalog = $this->createMock(\Masilia\AiAssistant\Agent\Block\BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([]);
        $blockCatalog->method('getCapabilities')->willReturn([]);

        $promptBuilder = new LlmPromptBuilder($blockCatalog);
        $classifier = new IntentClassifier($aiClient, $promptBuilder);

        $result = $classifier->classify('create a page');

        self::assertNotNull($result);
        self::assertSame('create_page', $result['intent']);
        self::assertSame('Test', $result['parameters']['title']);
    }

    public function testClassifyReturnsNullOnLlmException(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('suggest')->willThrowException(new \RuntimeException('API error'));

        $blockCatalog = $this->createMock(\Masilia\AiAssistant\Agent\Block\BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([]);
        $blockCatalog->method('getCapabilities')->willReturn([]);

        $promptBuilder = new LlmPromptBuilder($blockCatalog);
        $classifier = new IntentClassifier($aiClient, $promptBuilder);

        $result = $classifier->classify('hello');

        self::assertNull($result);
    }

    public function testClassifyReturnsNullForInvalidJson(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $aiClient->method('suggest')->willReturn('not json at all');

        $blockCatalog = $this->createMock(\Masilia\AiAssistant\Agent\Block\BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([]);
        $blockCatalog->method('getCapabilities')->willReturn([]);

        $promptBuilder = new LlmPromptBuilder($blockCatalog);
        $classifier = new IntentClassifier($aiClient, $promptBuilder);

        $result = $classifier->classify('hello');

        self::assertNull($result);
    }

    public function testGetSupportedIntentsReturnsAllIntents(): void
    {
        $aiClient = $this->createMock(AiClientInterface::class);
        $blockCatalog = $this->createMock(\Masilia\AiAssistant\Agent\Block\BlockCatalog::class);
        $blockCatalog->method('getAvailableBlocks')->willReturn([]);
        $blockCatalog->method('getCapabilities')->willReturn([]);

        $promptBuilder = new LlmPromptBuilder($blockCatalog);
        $classifier = new IntentClassifier($aiClient, $promptBuilder);

        $intents = $classifier->getSupportedIntents();

        self::assertContains('create_page', $intents);
        self::assertContains('search_content', $intents);
        self::assertContains('undo', $intents);
        self::assertContains('list_blocks', $intents);
        self::assertCount(8, $intents);
    }
}
