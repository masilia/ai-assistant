<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Canonical field identifiers used throughout the AI assistant.
 *
 * These constants represent common field identifiers that appear across
 * multiple content types. They serve as a single source of truth to
 * replace magic strings in tool implementations.
 *
 * Note: Not all content types have all these fields. Tools should verify
 * field existence via ContentType::hasFieldDefinition() when needed.
 */
final class FieldId
{
    /** Common title field identifier */
    public const TITLE = 'title';

    /** Common name field identifier (used by folders) */
    public const NAME = 'name';

    /** Description or summary field */
    public const DESCRIPTION = 'description';

    /** Backoffice title field (used by layout content types) */
    public const BO_TITLE = 'bo_title';

    /** Domain field (used by site content types) */
    public const DOMAIN = 'domain';

    /** Blocks relation list field (used by page content types) */
    public const BLOCKS = 'blocks';

    /** Favicon image field (used by site content types) */
    public const FAVICON = 'favicon';
}
