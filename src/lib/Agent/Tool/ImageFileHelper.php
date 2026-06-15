<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

use RuntimeException;

/**
 * Shared utility for saving base64-encoded image data to temporary files.
 */
final class ImageFileHelper
{
    /**
     * Decode base64 image data and save to a temp file.
     *
     * @throws RuntimeException
     */
    public static function saveTempFile(string $imageData, string $mimeType, string $prefix = 'ai_img_'): string
    {
        $ext = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };

        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            throw new RuntimeException('Failed to decode image data');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($tmpFile === false) {
            throw new RuntimeException('Failed to create temp file');
        }

        $path = $tmpFile . '.' . $ext;
        rename($tmpFile, $path);

        if (file_put_contents($path, $decoded) === false) {
            throw new RuntimeException(sprintf('Failed to write temp file: %s', $path));
        }

        return $path;
    }
}
