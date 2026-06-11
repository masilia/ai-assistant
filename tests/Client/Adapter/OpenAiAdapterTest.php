<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\OpenAiAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpenAiAdapterTest extends TestCase
{
    private OpenAiAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new OpenAiAdapter();
    }

    public function testSupports(): void
    {
        self::assertTrue($this->adapter->supports('openai'));
        self::assertFalse($this->adapter->supports('anthropic'));
    }

    public function testBuildEndpointUrlUsesDefault(): void
    {
        self::assertSame(
            'https://api.openai.com/v1/chat/completions',
            $this->adapter->buildEndpointUrl(null)
        );
    }

    public function testBuildEndpointUrlExtractsHostFromCustomUrl(): void
    {
        self::assertSame(
            'http://localhost:1234/v1/chat/completions',
            $this->adapter->buildEndpointUrl('http://localhost:1234/v1/')
        );
    }

    public function testBuildEndpointUrlExtractsHostAndAppendsPath(): void
    {
        self::assertSame(
            'http://localhost:1234/v1/chat/completions',
            $this->adapter->buildEndpointUrl('http://localhost:1234/v1/chat/completions')
        );
    }

    public function testBuildEndpointUrlFromHostOnly(): void
    {
        self::assertSame(
            'http://localhost:1234/v1/chat/completions',
            $this->adapter->buildEndpointUrl('http://localhost:1234')
        );
    }

    public function testBuildHeadersIncludesBearerWhenKeyPresent(): void
    {
        $headers = $this->adapter->buildHeaders('sk-test');

        self::assertSame('Bearer sk-test', $headers['Authorization']);
        self::assertSame('application/json', $headers['Content-Type']);
    }

    public function testBuildHeadersOmitsAuthWhenKeyMissing(): void
    {
        self::assertArrayNotHasKey('Authorization', $this->adapter->buildHeaders(null));
    }

    public function testBuildRequestBody(): void
    {
        $body = $this->adapter->buildRequestBody('gpt-4o', 0.5, 100, 'sys', 'user');

        self::assertSame('gpt-4o', $body['model']);
        self::assertSame(0.5, $body['temperature']);
        self::assertSame(100, $body['max_tokens']);
        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('user', $body['messages'][1]['role']);
        self::assertArrayNotHasKey('stream', $body);
    }

    public function testBuildStreamRequestBodyEnablesStreaming(): void
    {
        $body = $this->adapter->buildStreamRequestBody('gpt-4o', 0.5, 100, 'sys', 'user');

        self::assertTrue($body['stream']);
    }

    public function testParseResponse(): void
    {
        $data = ['choices' => [['message' => ['content' => '  Hello  ']]]];

        self::assertSame('Hello', $this->adapter->parseResponse($data));
    }

    public function testParseResponseThrowsOnEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->adapter->parseResponse(['choices' => [['message' => ['content' => '']]]]);
    }

    public function testParseStreamChunk(): void
    {
        $line = 'data: ' . json_encode(['choices' => [['delta' => ['content' => 'Hi']]]]);

        self::assertSame('Hi', $this->adapter->parseStreamChunk($line));
    }

    public function testParseStreamChunkReturnsNullForNonData(): void
    {
        self::assertNull($this->adapter->parseStreamChunk(': keep-alive'));
        self::assertNull($this->adapter->parseStreamChunk('data: [DONE]'));
    }

    public function testIsStreamEnd(): void
    {
        self::assertTrue($this->adapter->isStreamEnd('data: [DONE]'));
        self::assertFalse($this->adapter->isStreamEnd('data: {"x":1}'));
    }
}
