<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\MiniMaxAdapter;
use PHPUnit\Framework\TestCase;

final class MiniMaxAdapterTest extends TestCase
{
    private MiniMaxAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MiniMaxAdapter();
    }

    public function testSupports(): void
    {
        self::assertTrue($this->adapter->supports('minimax'));
        self::assertFalse($this->adapter->supports('anthropic'));
    }

    public function testBuildEndpointUrl(): void
    {
        self::assertSame(
            'https://api.minimax.io/anthropic/v1/messages',
            $this->adapter->buildEndpointUrl(null)
        );
    }

    public function testBuildHeadersUsesXApiKey(): void
    {
        $headers = $this->adapter->buildHeaders('mm-key');

        self::assertSame('mm-key', $headers['X-Api-Key']);
        self::assertArrayNotHasKey('anthropic-version', $headers);
    }

    public function testBuildRequestBodyClampsTemperature(): void
    {
        $body = $this->adapter->buildRequestBody('m2', 0.0, 100, 'sys', 'user');

        self::assertSame(0.01, $body['temperature']);
        self::assertSame('sys', $body['system']);
    }

    public function testBuildStreamRequestBodyClampsAndStreams(): void
    {
        $body = $this->adapter->buildStreamRequestBody('m2', 0.0, 100, 'sys', 'user');

        self::assertSame(0.01, $body['temperature']);
        self::assertTrue($body['stream']);
    }
}
