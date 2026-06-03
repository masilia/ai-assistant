<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\AnthropicAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AnthropicAdapterTest extends TestCase
{
    private AnthropicAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new AnthropicAdapter();
    }

    public function testSupports(): void
    {
        self::assertTrue($this->adapter->supports('anthropic'));
        self::assertFalse($this->adapter->supports('openai'));
    }

    public function testBuildEndpointUrl(): void
    {
        self::assertSame('https://api.anthropic.com/v1/messages', $this->adapter->buildEndpointUrl(null));
    }

    public function testBuildHeadersUsesApiKeyHeader(): void
    {
        $headers = $this->adapter->buildHeaders('key-123');

        self::assertSame('key-123', $headers['x-api-key']);
        self::assertSame('2023-06-01', $headers['anthropic-version']);
    }

    public function testBuildRequestBodyUsesSystemField(): void
    {
        $body = $this->adapter->buildRequestBody('claude', 0.5, 100, 'sys', 'user');

        self::assertSame('sys', $body['system']);
        self::assertSame('user', $body['messages'][0]['role']);
    }

    public function testBuildStreamRequestBodyClampsTemperature(): void
    {
        $body = $this->adapter->buildStreamRequestBody('claude', 0.0, 100, 'sys', 'user');

        self::assertSame(0.01, $body['temperature']);
        self::assertTrue($body['stream']);
    }

    public function testParseResponseExtractsTextBlock(): void
    {
        $data = ['content' => [
            ['type' => 'thinking', 'text' => 'hmm'],
            ['type' => 'text', 'text' => '  Answer  '],
        ]];

        self::assertSame('Answer', $this->adapter->parseResponse($data));
    }

    public function testParseResponseThrowsOnEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->adapter->parseResponse(['content' => []]);
    }

    public function testParseStreamChunkExtractsDelta(): void
    {
        $line = 'data: ' . json_encode(['type' => 'content_block_delta', 'delta' => ['text' => 'Hi']]);

        self::assertSame('Hi', $this->adapter->parseStreamChunk($line));
    }

    public function testIsStreamEndOnMessageStop(): void
    {
        self::assertTrue($this->adapter->isStreamEnd('event: message_stop'));
        self::assertFalse($this->adapter->isStreamEnd('event: content_block_delta'));
    }
}
