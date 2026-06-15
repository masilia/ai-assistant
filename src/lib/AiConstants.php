<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

final class AiConstants
{
    public const MAX_SIBLING_CHARS = 250;

    public const MAX_CURRENT_VALUE_CHARS = 500;

    public const MAX_ALT_TEXT_CHARS = 125;

    public const DEFAULT_SITEACCESS = 'default';

    /**
     * Truncate a string to a maximum length, appending '...' if truncated.
     */
    public static function truncate(string $value, int $maxChars): string
    {
        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, $maxChars) . '...';
    }

    /**
     * Scrub a string value for safe inclusion in LLM prompts.
     * Collapses newlines into spaces and escapes double quotes.
     */
    public static function scrubForPrompt(string $value): string
    {
        return str_replace(['"', "\n", "\r"], ['\\"', ' ', ''], $value);
    }
}
