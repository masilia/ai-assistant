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

    private function makeFieldDef(): FieldDefinition
    {
        $fieldDef = $this->createMock(FieldDefinition::class);
        $fieldDef->method('getFieldTypeIdentifier')->willReturn('ezmatrix');

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
}
