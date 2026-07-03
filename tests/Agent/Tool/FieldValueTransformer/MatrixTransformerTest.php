<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeMatrix\FieldType\Value\Row;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\MatrixTransformer;
use PHPUnit\Framework\TestCase;

final class MatrixTransformerTest extends TestCase
{
    private static bool $matrixAutoloaderRegistered = false;

    protected function setUp(): void
    {
        // The matrix field type ships with the host Ibexa app, not this package.
        // When the class isn't on the package's autoloader, fall back to the
        // host app's vendor (typical in the ddev dev environment).
        if (class_exists(Row::class)) {
            return;
        }

        self::registerMatrixFallbackAutoloader();

        if (!class_exists(Row::class)) {
            self::markTestSkipped('Ibexa matrix field type not available in this test environment.');
        }
    }

    private static function registerMatrixFallbackAutoloader(): void
    {
        if (self::$matrixAutoloaderRegistered) {
            return;
        }
        self::$matrixAutoloaderRegistered = true;

        // Try a couple of common locations for the host app's vendor.
        // __DIR__ is tests/Agent/Tool/FieldValueTransformer — 7 levels up reaches the workspace root.
        $candidates = [
            __DIR__ . '/../../../../../../ibexa/vendor/ibexa/fieldtype-matrix/src/lib',
            __DIR__ . '/../../../../../../../ibexa/vendor/ibexa/fieldtype-matrix/src/lib',
        ];

        $matrixPath = null;
        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $matrixPath = $candidate;
                break;
            }
        }

        if ($matrixPath === null) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($matrixPath): void {
            $prefix = 'Ibexa\\FieldTypeMatrix\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path = $matrixPath . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require $path;
            }
        });
    }

    private function makeFieldDef(array $columns = []): FieldDefinition
    {
        $fieldDef = $this->createMock(FieldDefinition::class);
        $fieldDef->method('getFieldTypeIdentifier')->willReturn('ezmatrix');
        $fieldDef->fieldSettings = ['columns' => $columns];

        return $fieldDef;
    }

    public function testFlatArrayOfRowObjectsIsWrappedIntoRowInstances(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            ['col1' => 'a', 'col2' => 'b'],
            ['col1' => 'c', 'col2' => 'd'],
        ]);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Row::class, $result);
        self::assertSame(['col1' => 'a', 'col2' => 'b'], $result[0]->getCells());
    }

    public function testNestedRowsKeyIsUnwrapped(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            'rows' => [
                ['a' => '1'],
                ['a' => '2'],
            ],
        ]);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Row::class, $result);
    }

    public function testSingleRowObjectIsWrapped(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            'col1' => 'value',
        ]);

        self::assertCount(1, $result);
        self::assertInstanceOf(Row::class, $result[0]);
        self::assertSame(['col1' => 'value'], $result[0]->getCells());
    }

    public function testAlreadyRowObjectsPassThrough(): void
    {
        $transformer = new MatrixTransformer();
        $row = new Row(['x' => 'y']);

        $result = $transformer->transform($this->makeFieldDef(), [$row]);

        self::assertSame([$row], $result);
    }

    public function testEmptyValueReturnsEmptyArray(): void
    {
        $transformer = new MatrixTransformer();

        self::assertSame([], $transformer->transform($this->makeFieldDef(), []));
    }

    public function testArrayCellValueIsJoinedToString(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            ['bullets' => ['line one', 'line two', 'line three']],
        ]);

        self::assertCount(1, $result);
        self::assertSame(
            ['bullets' => "line one\nline two\nline three"],
            $result[0]->getCells(),
        );
    }

    public function testNestedArrayCellValueIsJsonEncoded(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            ['payload' => ['nested' => 'object', 'count' => 2]],
        ]);

        self::assertCount(1, $result);
        $cells = $result[0]->getCells();
        self::assertSame(
            ['payload' => '{"nested":"object","count":2}'],
            $cells,
        );
    }

    public function testNumericCellValueIsCastToString(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            ['count' => 42, 'ratio' => 0.75],
        ]);

        self::assertCount(1, $result);
        self::assertSame(
            ['count' => '42', 'ratio' => '0.75'],
            $result[0]->getCells(),
        );
    }

    public function testNullAndFalseCellsBecomeEmptyString(): void
    {
        $transformer = new MatrixTransformer();

        $result = $transformer->transform($this->makeFieldDef(), [
            ['empty' => null, 'flag' => false],
        ]);

        self::assertCount(1, $result);
        self::assertSame(
            ['empty' => '', 'flag' => ''],
            $result[0]->getCells(),
        );
    }

    public function testSanitisedRowsAreUsableByRowIsEmpty(): void
    {
        $transformer = new MatrixTransformer();

        // If sanitisation failed, Row::isEmpty() would TypeError on the array cell.
        $result = $transformer->transform($this->makeFieldDef(), [
            ['bullets' => ['a', 'b']],
        ]);

        self::assertFalse($result[0]->isEmpty());
    }

    public function testJsonWrappedSingleCellIsUnwrappedToCorrectColumns(): void
    {
        $transformer = new MatrixTransformer();

        $columns = [
            ['identifier' => 'text', 'name' => 'Button Text'],
            ['identifier' => 'url', 'name' => 'Button URL'],
            ['identifier' => 'style', 'name' => 'Button Style'],
        ];

        // LLM sent: [{item: "[{\"text\":\"Click\",\"url\":\"/page\",\"style\":\"btn-primary\"}]"}]
        $jsonRows = json_encode([
            ['text' => 'Click me', 'url' => '/page', 'style' => 'btn-primary'],
            ['text' => 'Learn more', 'url' => '/about', 'style' => 'btn-outline'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $transformer->transform($this->makeFieldDef($columns), [
            ['item' => $jsonRows],
        ]);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Row::class, $result);
        self::assertSame(['text' => 'Click me', 'url' => '/page', 'style' => 'btn-primary'], $result[0]->getCells());
        self::assertSame(['text' => 'Learn more', 'url' => '/about', 'style' => 'btn-outline'], $result[1]->getCells());
    }

    public function testJsonUnwrapFailsWhenDecodedRowsDontMatchColumns(): void
    {
        $transformer = new MatrixTransformer();

        $columns = [
            ['identifier' => 'text', 'name' => 'Text'],
            ['identifier' => 'url', 'name' => 'URL'],
        ];

        // JSON decodes to objects with keys that don't match any column identifier
        $jsonRows = json_encode([
            ['foo' => 'bar', 'baz' => 'qux'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $transformer->transform($this->makeFieldDef($columns), [
            ['item' => $jsonRows],
        ]);

        // Should fall back to treating it as a regular row with a single 'item' cell
        self::assertCount(1, $result);
        self::assertSame(['item' => $jsonRows], $result[0]->getCells());
    }

    public function testNonJsonSingleCellIsNotUnwrapped(): void
    {
        $transformer = new MatrixTransformer();

        $columns = [
            ['identifier' => 'text', 'name' => 'Text'],
        ];

        $result = $transformer->transform($this->makeFieldDef($columns), [
            ['text' => 'just a plain string'],
        ]);

        self::assertCount(1, $result);
        self::assertSame(['text' => 'just a plain string'], $result[0]->getCells());
    }
}
