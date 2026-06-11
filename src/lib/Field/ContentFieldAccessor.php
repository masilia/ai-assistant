<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;

/**
 * Small helpers for fetching a single {@see Field} from a {@see Content}.
 *
 * Centralises the "try the requested language, fall back to the default
 * translation" pattern that was previously inlined in three places
 * (FieldContextExtractor + SiblingFieldsExtractor). Pure functions — no
 * DI, no I/O, no logging.
 */
final class ContentFieldAccessor
{
    /**
     * Returns the field for the requested identifier in the requested
     * language, or the default-language field if the requested one is
     * absent (the typical "untranslated source" case for the
     * "translate from X" flow).
     */
    public static function getWithFallback(Content $content, string $identifier, string $language): ?Field
    {
        if ($language !== '') {
            $field = $content->getField($identifier, $language);
            if ($field !== null) {
                return $field;
            }
        }

        return $content->getField($identifier);
    }
}
