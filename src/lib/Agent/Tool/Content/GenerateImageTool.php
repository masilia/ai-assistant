<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ImageFileHelper;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;
use RuntimeException;

readonly class GenerateImageTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ImageGenerationClient $imageClient,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::GENERATE_IMAGE;
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
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            // 1. Generate image via provider
            $imageResult = $this->imageClient->generate($prompt, $size);

            // 2. Save base64 to temp file
            $tempPath = ImageFileHelper::saveTempFile($imageResult->imageData, $imageResult->mimeType);

            try {
                // 3. Update content's image field
                $result = $this->repository->sudo(function () use ($contentId, $fieldIdentifier, $tempPath, $languageCode) {
                    $contentService = $this->repository->getContentService();

                    $content = $contentService->loadContent($contentId);
                    $draft = $contentService->createContentDraft($content->contentInfo);

                    $updateStruct = $contentService->newContentUpdateStruct();
                    $updateStruct->initialLanguageCode = $languageCode;
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
        } catch (RuntimeException $e) {
            $this->aiLogger->error('[Agent] generate image: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ToolResult::error('Image generation failed: provider returned an error');
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'generate image');
        }
    }

}
