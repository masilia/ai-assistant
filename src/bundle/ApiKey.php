<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant;

/**
 * Constants for the AI provider API-key flow on the bundle side.
 *
 * The frontend dashboard masks the stored API key in any list / edit
 * view as {@see MASK} and re-sends that exact string on save to signal
 * "do not change the stored key". If the mask and the skip-rewrite
 * sentinel ever drift, the dashboard would silently overwrite the saved
 * key. Keep both sites pointed at this constant.
 */
final class ApiKey
{
    public const MASK = '••••••••';
}
