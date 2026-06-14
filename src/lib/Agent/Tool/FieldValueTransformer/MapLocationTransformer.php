<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

/**
 * Normalizes LLM output into the format expected by ezgmaplocation fields.
 *
 * Accepts: {lat, lng/lon, address} or {latitude, longitude, address}.
 * Returns: ['latitude' => float, 'longitude' => float, 'address' => string].
 */
readonly class MapLocationTransformer implements FieldValueTransformerInterface
{
    public function getFieldType(): string
    {
        return 'ezgmaplocation';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $latitude = $value['latitude'] ?? $value['lat'] ?? null;
        $longitude = $value['longitude'] ?? $value['lng'] ?? $value['lon'] ?? null;
        $address = $value['address'] ?? '';

        if ($latitude === null || $longitude === null) {
            return $value;
        }

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'address' => (string) $address,
        ];
    }
}
