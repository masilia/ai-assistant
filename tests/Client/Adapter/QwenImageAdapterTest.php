<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\QwenImageAdapter;
use PHPUnit\Framework\TestCase;

final class QwenImageAdapterTest extends TestCase
{
    private QwenImageAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new QwenImageAdapter();
    }

    public function testSupportsQwenProvider(): void
    {
        self::assertTrue($this->adapter->supportsImageGeneration('qwen'));
    }

    public function testDoesNotSupportOtherProviders(): void
    {
        self::assertFalse($this->adapter->supportsImageGeneration('openai'));
        self::assertFalse($this->adapter->supportsImageGeneration('minimax'));
        self::assertFalse($this->adapter->supportsImageGeneration('anthropic'));
    }

    public function testBuildEndpointUrlDefault(): void
    {
        self::assertSame(
            'https://dashscope.aliyuncs.com/api/v1/services/aigc/multimodal-generation/generation',
            $this->adapter->buildEndpointUrl(null)
        );
    }

    public function testBuildEndpointUrlCustom(): void
    {
        self::assertSame(
            'https://custom.api.com/api/v1/services/aigc/multimodal-generation/generation',
            $this->adapter->buildEndpointUrl('https://custom.api.com/v1')
        );
    }

    public function testBuildHeadersWithApiKey(): void
    {
        $headers = $this->adapter->buildHeaders('sk-test-123');

        self::assertSame('application/json', $headers['Content-Type']);
        self::assertSame('Bearer sk-test-123', $headers['Authorization']);
    }

    public function testBuildHeadersWithoutApiKey(): void
    {
        $headers = $this->adapter->buildHeaders(null);

        self::assertSame('application/json', $headers['Content-Type']);
        self::assertArrayNotHasKey('Authorization', $headers);
    }

    public function testBuildImageRequestBodyMinimal(): void
    {
        $body = $this->adapter->buildImageRequestBody(
            'A cat sitting on a mat',
            'qwen-image-2.0-pro',
        );

        self::assertSame('qwen-image-2.0-pro', $body['model']);
        self::assertSame('A cat sitting on a mat', $body['input']['messages'][0]['content'][0]['text']);
        self::assertSame('user', $body['input']['messages'][0]['role']);
        self::assertSame('2048*2048', $body['parameters']['size']);
        self::assertSame(1, $body['parameters']['n']);
    }

    public function testBuildImageRequestBodyWithSize(): void
    {
        $body = $this->adapter->buildImageRequestBody(
            'A sunset',
            'qwen-image-2.0-pro',
            '1024x1024',
        );

        self::assertSame('1024*1024', $body['parameters']['size']);
    }

    public function testBuildImageRequestBodyConvertsXToAsterisk(): void
    {
        $body = $this->adapter->buildImageRequestBody(
            'A sunset',
            'qwen-image-2.0',
            '1792x1024',
        );

        self::assertSame('1792*1024', $body['parameters']['size']);
    }

    public function testBuildImageRequestBodyWithAsteriskSizeUnchanged(): void
    {
        $body = $this->adapter->buildImageRequestBody(
            'A sunset',
            'qwen-image-2.0',
            '2048*2048',
        );

        self::assertSame('2048*2048', $body['parameters']['size']);
    }

    public function testParseImageResponseWithUrl(): void
    {
        // Qwen returns image URLs — parseImageResponse downloads them.
        // We test the structure validation only (no HTTP call).
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download image from Qwen URL');

        // Pass a non-existent URL to trigger the download failure
        $this->adapter->parseImageResponse([
            'output' => [
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                ['image' => 'https://nonexistent.invalid/image.png'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testParseImageResponseThrowsOnEmptyChoices(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned no choices');

        $this->adapter->parseImageResponse([
            'output' => [
                'choices' => [],
            ],
        ]);
    }

    public function testParseImageResponseThrowsOnMissingOutput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned no choices');

        $this->adapter->parseImageResponse([]);
    }

    public function testParseImageResponseThrowsOnEmptyContent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned empty content');

        $this->adapter->parseImageResponse([
            'output' => [
                'choices' => [
                    [
                        'message' => [
                            'content' => [],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testParseImageResponseThrowsOnNoImageUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned no image URL');

        $this->adapter->parseImageResponse([
            'output' => [
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                ['text' => 'no image here'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testGetSupportedSizesContainsExpectedEntries(): void
    {
        $sizes = $this->adapter->getSupportedSizes();

        self::assertContains('2048*2048', $sizes);
        self::assertContains('2688*1536', $sizes);
        self::assertContains('1664*928', $sizes);
        self::assertContains('1328*1328', $sizes);
        self::assertCount(10, $sizes);
    }

    public function testGetDefaultImageModel(): void
    {
        self::assertSame('qwen-image-2.0-pro', $this->adapter->getDefaultImageModel());
    }
}
