<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests;

use Masilia\AiAssistant\LanguageNormalizer;
use PHPUnit\Framework\TestCase;

final class LanguageNormalizerTest extends TestCase
{
    private LanguageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new LanguageNormalizer();
    }

    /**
     * @dataProvider locales
     */
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'ibexa eng-GB' => ['eng-GB', 'en'],
            'fre-FR' => ['fre-FR', 'fre'],
            'short en' => ['en', 'en'],
            'underscore' => ['en_US', 'en'],
            'empty defaults to en' => ['', 'en'],
        ];
    }
}
