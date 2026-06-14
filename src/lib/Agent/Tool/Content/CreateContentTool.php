<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class CreateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private FieldValueTransformerRegistry $transformerRegistry,
        private ConfigResolverInterface $configResolver,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'create_content';
    }

    public function getDescription(): string
    {
        return 'Create a new content item in Ibexa. Returns the created content ID and location ID.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier (e.g., "article", "page", "paragraph")',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID where the content will be created',
                ],
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Siteaccess name — resolves parent from siteaccess root',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Field values as key-value pairs',
                ],
                'remote_id' => [
                    'type' => 'string',
                    'description' => 'Optional remote ID for the content',
                ],
                'location_remote_id' => [
                    'type' => 'string',
                    'description' => 'Optional remote ID for the location',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['content_type', 'attributes'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentTypeIdentifier = $params['content_type'];
            $attributes = $params['attributes'] ?? [];
            $languageCode = $params['language'] ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();
            $remoteId = $params['remote_id'] ?? null;
            $locationRemoteId = $params['location_remote_id'] ?? null;

            // Resolve parent_location_id from siteaccess if not provided directly
            $parentLocationId = isset($params['parent_location_id'])
                ? (int) $params['parent_location_id']
                : $this->resolveParentLocation($params['siteaccess'] ?? '');

            if ($parentLocationId === null) {
                return ToolResult::error('Provide either parent_location_id or a siteaccess name to resolve the parent location.');
            }

            $contentService = $this->repository->getContentService();
            $locationService = $this->repository->getLocationService();
            $contentTypeService = $this->repository->getContentTypeService();

            // Load content type
            $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

            // Create content draft with inline location
            $createStruct = $contentService->newContentCreateStruct($contentType, $languageCode);
            $createStruct->contentType = $contentType;
            $createStruct->mainLanguageCode = $languageCode;
            $createStruct->remoteId = $remoteId;

            // Set field values with transformation
            foreach ($attributes as $fieldIdentifier => $value) {
                $fieldDef = $contentType->getFieldDefinition($fieldIdentifier);
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
                $createStruct->setField($fieldIdentifier, $transformedValue, $languageCode);
            }

            $locationCreateStruct = $locationService->newLocationCreateStruct($parentLocationId);
            $locationCreateStruct->remoteId = $locationRemoteId;

            $draft = $contentService->createContent($createStruct, [$locationCreateStruct]);

            // Publish
            $published = $contentService->publishVersion($draft->versionInfo);
            $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

            $result = [
                'content_id' => $published->id,
                'location_id' => $location->id,
                'remote_id' => $published->remoteId,
            ];

            return ToolResult::ok(
                sprintf('Created %s (ID: %d)', $contentTypeIdentifier, $result['content_id']),
                $result,
            );
        } catch (ContentFieldValidationException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create content');
        } catch (BadStateException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create content');
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('create content');
        } catch (NotFoundException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create content');
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create content');
        }
    }

    private function resolveParentLocation(string $siteaccess): ?int
    {
        if ($siteaccess === '') {
            // Current request siteaccess
            try {
                return (int) $this->configResolver->getParameter('content.tree_root.location_id');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return (int) $this->configResolver->getParameter(
                'content.tree_root.location_id',
                null,
                $siteaccess,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
