<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\AutomatedTranslation\Encoder\Field;

use Ibexa\Contracts\AutomatedTranslation\Encoder\Field\FieldEncoderInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Core\FieldType\Value;
use Ibexa\FieldTypeMatrix\FieldType\Value as MatrixValue;
use Ibexa\FieldTypeMatrix\FieldType\Value\Row;
use RuntimeException;

/**
 * Encodes ezmatrix fields as a JSON hash for translation.
 *
 * Format: {"entries":[{"col1":"val1","col2":"val2"},…]}
 * The entries key mirrors the MatrixConverter's hash format so the
 * decoded value is structurally identical to the original.
 */
final class MatrixFieldEncoder implements FieldEncoderInterface
{
    public function canEncode(Field $field): bool
    {
        return $field->value instanceof MatrixValue;
    }

    public function canDecode(string $type): bool
    {
        return is_a($type, MatrixValue::class, true);
    }

    public function encode(Field $field): string
    {
        /** @var MatrixValue $value */
        $value = $field->value;
        $entries = [];

        foreach ($value->getRows() as $row) {
            $entries[] = $row->getCells();
        }

        $encoded = json_encode(['entries' => $entries], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('MatrixFieldEncoder: failed to encode matrix value as JSON.');
        }

        return $encoded;
    }

    /**
     * @param mixed $previousFieldValue
     */
    public function decode(string $value, $previousFieldValue): Value
    {
        $decoded = json_decode($value, true);

        if (!is_array($decoded) || !isset($decoded['entries']) || !is_array($decoded['entries'])) {
            if ($previousFieldValue instanceof MatrixValue) {
                return $previousFieldValue;
            }

            return new MatrixValue();
        }

        $rows = [];
        foreach ($decoded['entries'] as $cells) {
            if (is_array($cells)) {
                $rows[] = new Row($cells);
            }
        }

        return new MatrixValue($rows);
    }
}
