<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

/**
 * Canonical intent name identifiers.
 *
 * Used by IntentClassifier, LlmResponseParser validation, and
 * AgentOrchestrator dispatch. Centralising here prevents silent drift
 * when adding or renaming intents.
 */
final class IntentName
{
    public const CREATE_PAGE = 'create_page';
    public const CREATE_CONTENT = 'create_content';
    public const UPDATE_CONTENT = 'update_content';
    public const DELETE_CONTENT = 'delete_content';
    public const SEARCH_CONTENT = 'search_content';
    public const GENERATE_IMAGE = 'generate_image';
    public const LIST_BLOCKS = 'list_blocks';
    public const UNDO = 'undo';
    public const BROWSE_SITE_STRUCTURE = 'browse_site_structure';
    public const CREATE_SITE_STRUCTURE = 'create_site_structure';

    /**
     * All supported intent identifiers.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CREATE_PAGE,
            self::CREATE_CONTENT,
            self::UPDATE_CONTENT,
            self::DELETE_CONTENT,
            self::SEARCH_CONTENT,
            self::GENERATE_IMAGE,
            self::LIST_BLOCKS,
            self::UNDO,
            self::BROWSE_SITE_STRUCTURE,
            self::CREATE_SITE_STRUCTURE,
        ];
    }
}
