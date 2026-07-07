<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\AutomatedTranslation\Encoder\Field;

use Ibexa\Contracts\AutomatedTranslation\Encoder\Field\FieldEncoderInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Core\FieldType\Value;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\Value as MetasValue;
use Novactive\Bundle\eZSEOBundle\Core\Meta;
use RuntimeException;

/**
 * Encodes novaseometas fields as JSON for automated translation.
 *
 * Format: {"title":"Page Title","description":"A description",…}
 *
 * Image keys (og:image, twitter:image) and canonical are NOT sent for
 * translation — they are preserved unchanged from the source locale.
 */
final class NovaSeoMetasFieldEncoder implements FieldEncoderInterface
{
    /** Keys that must not be translated (images, canonical). */
    private const SKIPPED_KEYS = ['og:image', 'twitter:image', 'canonical'];

    public function canEncode(Field $field): bool
    {
        return $field->value instanceof MetasValue;
    }

    public function canDecode(string $type): bool
    {
        return is_a($type, MetasValue::class, true);
    }

    public function encode(Field $field): string
    {
        /** @var MetasValue $value */
        $value = $field->value;
        $entries = [];

        foreach ($value->metas as $meta) {
            $name = $meta->getName();
            if (in_array($name, self::SKIPPED_KEYS, true)) {
                continue;
            }
            $entries[$name] = $meta->getContent();
        }

        if ($entries === []) {
            return '{}';
        }

        $encoded = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('NovaSeoMetasFieldEncoder: failed to encode metas as JSON.');
        }

        return $encoded;
    }

    /**
     * @param mixed $previousFieldValue
     */
    public function decode(string $value, $previousFieldValue): Value
    {
        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            if ($previousFieldValue instanceof MetasValue) {
                return $previousFieldValue;
            }

            return new MetasValue([]);
        }

        // Collect translated text metas into a map for quick lookup.
        $translated = array_filter($decoded, static function ($content, $key) {
            return is_string($key) && is_string($content);
        }, ARRAY_FILTER_USE_BOTH);

        // Start from previous metas (preserves image/canonical keys that
        // were skipped during encoding) then overwrite with translations.
        $metas = [];

        if ($previousFieldValue instanceof MetasValue) {
            foreach ($previousFieldValue->metas as $meta) {
                $name = $meta->getName();
                $content = $translated[$name] ?? $meta->getContent();
                $metas[] = $this->createMeta($name, $content);
            }
        }

        // Append any new keys from the translated map that weren't in the source.
        $existingNames = array_column($metas, null);
        foreach ($translated as $name => $content) {
            $found = false;
            foreach ($metas as $existing) {
                if ($existing->getName() === $name) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $metas[] = $this->createMeta($name, $content);
            }
        }

        return new MetasValue($metas);
    }

    private function createMeta(string $name, string $content): Meta
    {
        $meta = new Meta();
        $meta->setName($name);
        $meta->setContent($content);
        $meta->setFieldType('text');

        return $meta;
    }
}
