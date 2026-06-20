<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;
use Masilia\AiAssistant\Agent\Tool\ImageFileHelper;
use Masilia\AiAssistant\Agent\Tool\TempFileRegistry;
use Masilia\AiAssistant\Client\ImageGeneratorInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Unified image handler: receive raw LLM data, generate the image,
 * wrap it in an Ibexa ImageValue.
 *
 * Accepts two value shapes from the LLM:
 *   1. Bare string  → treated as the image description (prompt).
 *   2. Object/array with keys:
 *        - description (or prompt / alt): the text prompt (required)
 *        - size:       image dimensions, e.g. "1024x1024", "1792x1024",
 *                      "16:9", "1:1". Provider-dependent.
 *        - quality:    image quality, e.g. "standard", "hd".
 *                      Provider-dependent (MiniMax ignores it).
 *
 * Flow:
 *   1. Pass through already-built ImageValue instances unchanged.
 *   2. Wrap existing file paths defensively (if the LLM ever returns
 *      a real path, hand it to Ibexa without regenerating).
 *   3. Generate an image from the description via the configured
 *      provider, save to a temp file, wrap in ImageValue.
 *   4. Return null if generation or wrapping fails — Ibexa creates
 *      the content with an empty image rather than blocking creation.
 *
 * Temp files are tracked via {@see TempFileRegistry} and cleaned up
 * by TempFileFlushListener on kernel.terminate / kernel.exception.
 */
readonly class ImageTransformer implements FieldValueTransformerInterface
{
    public function __construct(
        private ImageGeneratorInterface $imageClient,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getFieldTypeIdentifier(): string
    {
        return 'ezimage';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if ($value instanceof ImageValue) {
            return $value;
        }

        [$description, $size, $quality] = self::normalizeValue($value);

        if ($description === null || $description === '') {
            return $value;
        }

        if (file_exists($description) && is_file($description)) {
            try {
                return ImageValue::fromString($description);
            } catch (Throwable) {
                return $description;
            }
        }

        if (!$this->imageClient->isConfigured()) {
            $this->aiLogger->warning(sprintf(
                '[ImageTransformer] No image provider configured; passing description through for field "%s".',
                $fieldDef->identifier,
            ));

            return $value;
        }

        try {
            $imageResult = $this->imageClient->generate($description, $size, $quality);
            $tempPath = ImageFileHelper::saveTempFile($imageResult->imageData, $imageResult->mimeType);
        } catch (Throwable $e) {
            $this->aiLogger->warning(sprintf(
                '[ImageTransformer] Generation failed for field "%s": %s',
                $fieldDef->identifier,
                $e->getMessage(),
            ));

            return null;
        }

        TempFileRegistry::track($tempPath);

        try {
            return ImageValue::fromString($tempPath);
        } catch (Throwable $e) {
            $this->aiLogger->warning(sprintf(
                '[ImageTransformer] fromString() failed for field "%s": %s',
                $fieldDef->identifier,
                $e->getMessage(),
            ));

            return null;
        }
    }

    /**
     * Extract [description, size, quality] from the LLM's value.
     *
     * Accepts:
     *   - string: returned as [string, null, null] (backward compatible)
     *   - array with 'description' (or 'prompt' / 'alt') and optional
     *     'size' / 'quality' keys. Non-string size/quality are dropped.
     *   - anything else: returns [null, null, null] so the caller can
     *     pass the original value through unchanged.
     *
     * @return array{0: string|null, 1: ?string, 2: ?string}
     */
    private static function normalizeValue(mixed $value): array
    {
        if (is_string($value)) {
            return [$value, null, null];
        }

        if (!is_array($value)) {
            return [null, null, null];
        }

        $description = $value['description'] ?? $value['prompt'] ?? $value['alt'] ?? null;
        $size = (isset($value['size']) && is_string($value['size']) && $value['size'] !== '')
            ? $value['size']
            : null;
        $quality = (isset($value['quality']) && is_string($value['quality']) && $value['quality'] !== '')
            ? $value['quality']
            : null;

        return [
            is_string($description) && $description !== '' ? $description : null,
            $size,
            $quality,
        ];
    }
}
