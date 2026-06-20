<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\ImageTransformer;
use Masilia\AiAssistant\Agent\Tool\TempFileRegistry;
use Masilia\AiAssistant\Client\ImageGenerationResult;
use Masilia\AiAssistant\Client\ImageGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class ImageTransformerTest extends TestCase
{
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private static bool $imageAutoloaderRegistered = false;

    protected function setUp(): void
    {
        TempFileRegistry::reset();

        if (class_exists(ImageValue::class)) {
            return;
        }

        self::registerImageFallbackAutoloader();

        if (!class_exists(ImageValue::class)) {
            self::markTestSkipped('Ibexa Image\\Value not available in this test environment.');
        }
    }

    protected function tearDown(): void
    {
        TempFileRegistry::flush();
    }

    private static function registerImageFallbackAutoloader(): void
    {
        if (self::$imageAutoloaderRegistered) {
            return;
        }
        self::$imageAutoloaderRegistered = true;

        $candidates = [
            __DIR__ . '/../../../../../../ibexa/vendor/ibexa/core/src/lib',
            __DIR__ . '/../../../../../../../ibexa/vendor/ibexa/core/src/lib',
        ];

        $corePath = null;
        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $corePath = $candidate;
                break;
            }
        }

        if ($corePath === null) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($corePath): void {
            $prefix = 'Ibexa\\Core\\FieldType\\Image\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path = $corePath . '/FieldType/Image/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require $path;
            }
        });
    }

    private function makeFieldDef(string $identifier = 'image'): FieldDefinition
    {
        $fieldDef = $this->createMock(FieldDefinition::class);
        $fieldDef->method('getFieldTypeIdentifier')->willReturn('ezimage');
        $fieldDef->method('__get')->with('identifier')->willReturn($identifier);

        return $fieldDef;
    }

    private function makeImageClient(
        bool $configured,
        ?ImageGenerationResult $result = null,
        ?\Throwable $throw = null,
    ): ImageGeneratorInterface {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn($configured);
        if ($throw !== null) {
            $client->method('generate')->willThrowException($throw);
        } elseif ($result !== null) {
            $client->method('generate')->willReturn($result);
        }

        return $client;
    }

    public function testExistingImageValueIsPassedThrough(): void
    {
        $existing = ImageValue::fromString(__FILE__);
        $sut = new ImageTransformer(
            $this->makeImageClient(false),
            new NullLogger(),
        );

        $result = $sut->transform($this->makeFieldDef(), $existing);

        self::assertSame($existing, $result);
    }

    public function testNullIsPassedThrough(): void
    {
        $sut = new ImageTransformer(
            $this->makeImageClient(true),
            new NullLogger(),
        );

        self::assertNull($sut->transform($this->makeFieldDef(), null));
    }

    public function testNonStringScalarIsPassedThrough(): void
    {
        $sut = new ImageTransformer(
            $this->makeImageClient(true),
            new NullLogger(),
        );

        self::assertSame(42, $sut->transform($this->makeFieldDef(), 42));
        self::assertSame(3.14, $sut->transform($this->makeFieldDef(), 3.14));
        self::assertTrue($sut->transform($this->makeFieldDef(), true));
    }

    public function testEmptyStringIsPassedThrough(): void
    {
        $sut = new ImageTransformer(
            $this->makeImageClient(true),
            new NullLogger(),
        );

        self::assertSame('', $sut->transform($this->makeFieldDef(), ''));
    }

    public function testExistingFilePathIsWrappedInImageValue(): void
    {
        $sut = new ImageTransformer(
            $this->makeImageClient(true),
            new NullLogger(),
        );

        $result = $sut->transform($this->makeFieldDef(), __FILE__);

        self::assertInstanceOf(ImageValue::class, $result);
        self::assertSame(basename(__FILE__), $result->fileName);
    }

    public function testStringDescriptionGeneratesImageAndForwardsAllNullSizeAndQuality(): void
    {
        $client = $this->makeImageClient(true, new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', null, null)
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), 'A photo of a fossil exit');

        self::assertInstanceOf(ImageValue::class, $result);
        self::assertSame('png', pathinfo((string)$result->inputUri, PATHINFO_EXTENSION));
        self::assertCount(1, TempFileRegistry::tracked());
    }

    public function testObjectDescriptionIsExtractedForGenerateWithSizeAndQuality(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', '1024x1024', 'hd')
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), [
            'description' => 'A photo of a fossil exit',
            'size'        => '1024x1024',
            'quality'     => 'hd',
        ]);

        self::assertInstanceOf(ImageValue::class, $result);
    }

    public function testObjectWithPromptKeyIsNormalized(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', null, null)
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $sut->transform($this->makeFieldDef(), ['prompt' => 'A photo of a fossil exit']);
    }

    public function testObjectWithAltKeyIsNormalized(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', null, null)
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $sut->transform($this->makeFieldDef(), ['alt' => 'A photo of a fossil exit']);
    }

    public function testObjectWithoutDescriptionReturnsOriginalValue(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->expects(self::never())->method('generate');

        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), ['size' => '1024x1024']);

        self::assertSame(['size' => '1024x1024'], $result);
        self::assertSame([], TempFileRegistry::tracked());
    }

    public function testNonStringSizeAndQualityAreIgnored(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', null, null)
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $sut->transform($this->makeFieldDef(), [
            'description' => 'A photo of a fossil exit',
            'size'        => 123,
            'quality'     => ['hd'],
        ]);
    }

    public function testEmptySizeAndQualityAreTreatedAsNull(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('generate')
            ->with('A photo of a fossil exit', null, null)
            ->willReturn(new ImageGenerationResult(self::TINY_PNG_BASE64, 'image/png'));

        $sut = new ImageTransformer($client, new NullLogger());

        $sut->transform($this->makeFieldDef(), [
            'description' => 'A photo of a fossil exit',
            'size'        => '',
            'quality'     => '',
        ]);
    }

    public function testEmptyDescriptionWithSizeAndQualityReturnsOriginalValue(): void
    {
        $client = $this->createMock(ImageGeneratorInterface::class);
        $client->expects(self::never())->method('generate');

        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), [
            'description' => '',
            'size'        => '1024x1024',
        ]);

        self::assertSame(['description' => '', 'size' => '1024x1024'], $result);
    }

    public function testDescriptionPassesThroughWhenClientNotConfigured(): void
    {
        $sut = new ImageTransformer(
            $this->makeImageClient(false),
            new NullLogger(),
        );

        $result = $sut->transform($this->makeFieldDef(), 'A photo of a fossil exit');

        self::assertSame('A photo of a fossil exit', $result);
        self::assertSame([], TempFileRegistry::tracked());
    }

    public function testReturnsNullWhenGenerationThrows(): void
    {
        $client = $this->makeImageClient(true, null, new RuntimeException('API down'));
        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), 'A photo of a fossil exit');

        self::assertNull($result);
        self::assertSame([], TempFileRegistry::tracked());
    }

    public function testReturnsNullWhenSaveTempFileFails(): void
    {
        $client = $this->makeImageClient(true, new ImageGenerationResult('!!!not-valid-base64!!!', 'image/png'));
        $sut = new ImageTransformer($client, new NullLogger());

        $result = $sut->transform($this->makeFieldDef(), 'A photo of a fossil exit');

        self::assertNull($result);
    }

    public function testTempFileIsRegisteredAndCleanedOnFlush(): void
    {
        $base64 = self::TINY_PNG_BASE64;
        $client = $this->makeImageClient(true, new ImageGenerationResult($base64, 'image/png'));
        $sut = new ImageTransformer($client, new NullLogger());

        $sut->transform($this->makeFieldDef(), 'A photo of a fossil exit');

        $tracked = TempFileRegistry::tracked();
        self::assertCount(1, $tracked);
        self::assertFileExists($tracked[0]);

        TempFileRegistry::flush();

        self::assertFileDoesNotExist($tracked[0]);
        self::assertSame([], TempFileRegistry::tracked());
    }
}
