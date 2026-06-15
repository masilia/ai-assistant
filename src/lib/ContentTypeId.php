<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Canonical content type identifiers used throughout the AI assistant.
 *
 * These constants represent the default identifiers that align with
 * the bundle's Configuration defaults. They serve as:
 *   - Hardcoded replacements for magic strings in tools
 *   - Fallback defaults when configuration is not available
 *   - A single source of truth for content type naming
 *
 * Configuration can override these via:
 *   masilia_ai_assistant.system.<siteaccess>.*_content_type
 *
 * @see \Masilia\Bundle\AiAssistant\DependencyInjection\Configuration
 */
final class ContentTypeId
{
    /** Site container content type (default: 'site') */
    public const SITE = 'site';

    /** Home page content type (default: 'home_page') */
    public const HOME_PAGE = 'home_page';

    /** Standard page content type (default: 'page') */
    public const PAGE = 'page';

    /** Layout configuration content type (default: 'layout_config') */
    public const LAYOUT = 'layout_config';

    /** Folder container content type (default: 'folder') */
    public const FOLDER = 'folder';
}
