<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Single source of truth for the AI assistant's request-shape defaults.
 *
 * Used by:
 *   - the siteaccess-aware Configuration tree
 *   - the runtime fallback inside {@see \Masilia\AiAssistant\Client\TargetResolver}
 *   - the {@see \Masilia\Bundle\AiAssistant\Entity\AiModel} column defaults
 *   - the request-payload parsing in {@see \Masilia\Bundle\AiAssistant\Service\ModelManager}
 *
 * Keeping these in one place means a temperature / token / model change
 * only has to happen once for the whole package to follow.
 */
final class AiDefaults
{
    public const MODEL         = 'gpt-4o-mini';
    public const TEMPERATURE   = 0.7;
    public const MAX_TOKENS    = 4096;

    /**
     * Legacy column default on `app_ai_model.max_tokens` before the 1.0
     * refactor. The runtime default is `MAX_TOKENS` (4096); the column
     * default of 2048 is what the entity carries to satisfy existing
     * rows that have not been re-saved since the bump.
     */
    public const LEGACY_MAX_TOKENS = 2048;
}
