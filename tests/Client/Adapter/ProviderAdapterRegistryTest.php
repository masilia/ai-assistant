<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Client\Adapter;

use Masilia\AiAssistant\Client\Adapter\AnthropicAdapter;
use Masilia\AiAssistant\Client\Adapter\OpenAiAdapter;
use Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProviderAdapterRegistryTest extends TestCase
{
    public function testGetForProviderReturnsMatchingAdapter(): void
    {
        $registry = new ProviderAdapterRegistry([new OpenAiAdapter(), new AnthropicAdapter()]);

        self::assertInstanceOf(AnthropicAdapter::class, $registry->getForProvider('anthropic'));
        self::assertInstanceOf(OpenAiAdapter::class, $registry->getForProvider('openai'));
    }

    public function testGetForProviderThrowsWhenUnknown(): void
    {
        $registry = new ProviderAdapterRegistry([new OpenAiAdapter()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No AI provider adapter found for identifier "ollama"/');

        $registry->getForProvider('ollama');
    }

    public function testAcceptsTraversable(): void
    {
        $generator = (static function () {
            yield new OpenAiAdapter();
        })();

        $registry = new ProviderAdapterRegistry($generator);

        self::assertInstanceOf(OpenAiAdapter::class, $registry->getForProvider('openai'));
    }
}
