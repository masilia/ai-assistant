<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class UpdateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'update_content';
    }

    public function getDescription(): string
    {
        return 'Update fields on an existing content item.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to update',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Field values to update as key-value pairs',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['content_id', 'attributes'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentId = (int) $params['content_id'];
            $attributes = $params['attributes'] ?? [];
            $languageCode = $params['language'] ?? 'eng-GB';
            $contentService = $this->repository->getContentService();

            // Load current content
            $content = $contentService->loadContent($contentId);

            // Create draft
            $draft = $contentService->createContentDraft($content->contentInfo);

            // Create update struct
            $updateStruct = $contentService->newContentUpdateStruct();
            $updateStruct->languageCode = $languageCode;

            foreach ($attributes as $fieldIdentifier => $value) {
                $fieldDef = $content->getContentType()->getFieldDefinition($fieldIdentifier);
                $fieldType = $fieldDef?->fieldTypeIdentifier ?? '';

                // ezselection: map label strings to option indices
                if ($fieldType === 'ezselection' && is_string($value)) {
                    $options = $fieldDef->getFieldSettings()['options'] ?? [];
                    $labelToIndex = array_flip($options);
                    $index = $labelToIndex[$value] ?? null;
                    if ($index !== null) {
                        $value = [$index];
                    }
                }

                $transformedValue = $this->transformerRegistry->transform($fieldType, $fieldIdentifier, $value);
                $updateStruct->setField($fieldIdentifier, $transformedValue, $languageCode);
            }

            // Apply update
            $contentService->updateContent($draft->versionInfo, $updateStruct);

            // Publish
            $published = $contentService->publishVersion($draft->versionInfo);

            return ToolResult::ok(
                sprintf('Updated content %d', $contentId),
                [
                    'content_id' => $published->id,
                    'version_no' => $published->versionInfo->versionNo,
                ],
            );
        } catch (ContentFieldValidationException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'update content');
        } catch (BadStateException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'update content');
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('update content');
        } catch (NotFoundException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'update content');
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'update content');
        }
    }
}
