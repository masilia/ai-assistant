<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field;

/**
 * Canonical identifiers for every Ibexa field type the AI assistant
 * touches. Mirrors {@see \Masilia\AiAssistant\Client\ProviderId}: bare
 * string constants so that stringly-typed branches like
 * `if ($ctx->fieldType === 'ezmatrix')` become
 * `if ($ctx->fieldType === FieldType::Ezmatrix)`.
 *
 * Two groups of field types live here:
 *  - **AI-targeted** (the 5 the modal can act on directly):
 *    {@see EZSTRING}, {@see EZTEXT}, {@see EZRICHTEXT},
 *    {@see NOVASEOMETAS}, {@see EZMATRIX}. These are the field types
 *    the React SPA exposes the "AI" button on.
 *  - **Sibling-field context** (the rest): the stringifiers
 *    registered against these produce readable text for the AI prompt
 *    context block, but the modal does not target them directly.
 *
 * Keep the string value in lockstep with the Ibexa core field-type
 * identifier (the string that appears in
 * `FieldDefinition::fieldTypeIdentifier` and that
 * {@see FieldValueStringifierInterface::getSupportedFieldTypes()}
 * returns). Drift here breaks stringifier dispatch.
 */
final class FieldType
{
    public const EZSTRING        = 'ezstring';
    public const EZTEXT          = 'eztext';
    public const EZRICHTEXT      = 'ezrichtext';
    public const NOVASEOMETAS    = 'novaseometas';
    public const EZMATRIX        = 'ezmatrix';

    public const EZAUTHOR        = 'ezauthor';
    public const EZCOUNTRY       = 'ezcountry';
    public const EZIMAGE         = 'ezimage';
    public const EZIMAGEASSET    = 'ezimageasset';
    public const EZBINARYFILE    = 'ezbinaryfile';
    public const EZMEDIA         = 'ezmedia';
    public const EZKEYWORD       = 'ezkeyword';
    public const EZGMAPLOCATION  = 'ezgmaplocation';
    public const EZOBJECTRELATION        = 'ezobjectrelation';
    public const EZOBJECTRELATIONLIST    = 'ezobjectrelationlist';
    public const EZSELECTION     = 'ezselection';
    public const EZTAGS          = 'eztags';

    /**
     * Field types the AI modal exposes the "AI" button on.
     *
     * @return list<string>
     */
    public static function aiTargeted(): array
    {
        return [
            self::EZSTRING,
            self::EZTEXT,
            self::EZRICHTEXT,
            self::NOVASEOMETAS,
            self::EZMATRIX,
        ];
    }
}
