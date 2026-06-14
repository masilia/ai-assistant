<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use RuntimeException;
use Throwable;

readonly class GenerateImageTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ImageGenerationClient $imageClient,
    ) {
    }

    public function getName(): string
    {
        return 'generate_image';
    }

    public function getDescription(): string
    {
        return 'Generate an AI image from a text prompt and set it on an ezimage field of an existing content item.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID of the item to set the image on',
                ],
                'field' => [
                    'type' => 'string',
                    'description' => 'Field identifier for the image field (e.g. "image", "hero_image")',
                ],
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Text prompt describing the image to generate',
                ],
                'size' => [
                    'type' => 'string',
                    'description' => 'Aspect ratio: "1:1", "16:9", "9:16", "4:3", "3:4" (default: "1:1")',
                    'default' => '1:1',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['content_id', 'field', 'prompt'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentId = (int) $params['content_id'];
            $fieldIdentifier = $params['field'];
            $prompt = $params['prompt'];
            $size = $params['size'] ?? '1:1';
            $languageCode = $params['language'] ?? 'eng-GB';

            // 1. Generate image via provider
            $imageResult = $this->imageClient->generate($prompt, $size);

            // 2. Save base64 to temp file
            $tempPath = $this->saveTempFile($imageResult->imageData, $imageResult->mimeType);

            try {
                // 3. Update content's image field
                $result = $this->repository->sudo(function () use ($contentId, $fieldIdentifier, $tempPath, $languageCode) {
                    $contentService = $this->repository->getContentService();

                    $content = $contentService->loadContent($contentId);
                    $draft = $contentService->createContentDraft($content->contentInfo);

                    $updateStruct = $contentService->newContentUpdateStruct();
                    $updateStruct->languageCode = $languageCode;
                    $updateStruct->setField($fieldIdentifier, $tempPath, $languageCode);

                    $contentService->updateContent($draft->versionInfo, $updateStruct);
                    $published = $contentService->publishVersion($draft->versionInfo);

                    return [
                        'content_id' => $published->id,
                        'version_no' => $published->versionInfo->versionNo,
                    ];
                });
            } finally {
                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            $message = sprintf(
                'Generated image for field "%s" on content %d',
                $fieldIdentifier,
                $contentId,
            );
            if ($imageResult->revisedPrompt !== null) {
                $message .= sprintf(' (revised prompt: "%s")', $imageResult->revisedPrompt);
            }

            return ToolResult::ok($message, $result);
        } catch (Throwable $e) {
            return ToolResult::error(sprintf('Failed to generate image: %s', $e->getMessage()));
        }
    }

    /**
     * Decode base64 image data and save to a temp file.
     *
     * @throws RuntimeException
     */
    private function saveTempFile(string $imageData, string $mimeType): string
    {
        $ext = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = tempnam(sys_get_temp_dir(), 'ai_img_') . '.' . $ext;

        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            throw new RuntimeException('Failed to decode image data');
        }

        if (file_put_contents($path, $decoded) === false) {
            throw new RuntimeException(sprintf('Failed to write temp file: %s', $path));
        }

        return $path;
    }
}
