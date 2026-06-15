<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

/**
 * Canonical tool name identifiers.
 *
 * Used by tool getName(), IntentClassifier, AgentOrchestrator dispatch,
 * and error messages. Centralising here prevents silent drift.
 */
final class ToolName
{
    public const CREATE_CONTENT = 'create_content';
    public const UPDATE_CONTENT = 'update_content';
    public const SEARCH_CONTENT = 'search_content';
    public const LOAD_CONTENT = 'load_content';
    public const LOAD_CONTENT_TYPE = 'load_content_type';
    public const BROWSE_SITE_STRUCTURE = 'browse_site_structure';
    public const GENERATE_IMAGE = 'generate_image';
    public const LOAD_SITEACCESS = 'load_siteaccess';
    public const CREATE_FOLDER = 'create_folder';
    public const CREATE_SITE_STRUCTURE = 'create_site_structure';
    public const CREATE_PAGE_STRUCTURE = 'create_page_structure';
    public const TRASH_CONTENT = 'trash_content';
    public const RESTORE_CONTENT = 'restore_content';
    public const UNDO_LAST_OPERATION = 'undo_last_operation';
}
