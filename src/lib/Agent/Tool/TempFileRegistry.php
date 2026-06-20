<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

/**
 * Tracks temp files created by transformers for kernel-level cleanup.
 *
 * Used by ImageTransformer to register image temp files; flushed by
 * TempFileFlushListener on kernel.terminate / kernel.exception so that
 * temp files don't outlive a single request even if content creation
 * fails partway through.
 */
final class TempFileRegistry
{
    /** @var array<string, true> */
    private static array $tempFiles = [];

    public static function track(string $path): void
    {
        self::$tempFiles[$path] = true;
    }

    public static function flush(): void
    {
        foreach (array_keys(self::$tempFiles) as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        self::$tempFiles = [];
    }

    public static function reset(): void
    {
        self::$tempFiles = [];
    }

    /** @return string[] */
    public static function tracked(): array
    {
        return array_keys(self::$tempFiles);
    }
}
