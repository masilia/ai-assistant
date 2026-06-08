<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;

/**
 * Resolves the field identifier (e.g. "description") for a human-readable
 * field label coming from the frontend. The match is fuzzy: it tries
 * exact label match, then slugified-identifier match.
 *
 * Pure: no I/O, no logging, no entity access. Trivial to unit test.
 */
class FieldIdentifierResolver
{
    public function resolve(string $fieldName, ContentType $contentType): string
    {
        if ($fieldName === '') {
            return '';
        }

        $normalised = mb_strtolower(trim($fieldName));

        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            $defName = mb_strtolower(trim($fieldDef->getName() ?? ''));
            if ($defName === $normalised) {
                return $fieldDef->identifier;
            }
        }

        $asIdentifier = strtolower(str_replace(' ', '_', $fieldName));
        foreach ($contentType->getFieldDefinitions() as $fieldDef) {
            if ($fieldDef->identifier === $asIdentifier) {
                return $fieldDef->identifier;
            }
        }

        return $asIdentifier;
    }
}
