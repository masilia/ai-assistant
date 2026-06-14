<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class CreateFolderTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'create_folder';
    }

    public function getDescription(): string
    {
        return 'Create a folder content type in the content tree.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Folder name',
                ],
                'parent_location_id' => [
                    'type' => 'integer',
                    'description' => 'Parent location ID',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['name', 'parent_location_id'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentService = $this->repository->getContentService();
            $locationService = $this->repository->getLocationService();
            $contentTypeService = $this->repository->getContentTypeService();

            $languageCode = $params['language'] ?? 'eng-GB';

            $folderType = $contentTypeService->loadContentTypeByIdentifier('folder');

            $createStruct = $contentService->newContentCreateStruct();
            $createStruct->contentType = $folderType;
            $createStruct->mainLanguageCode = $languageCode;
            $createStruct->setField('name', $params['name'], $languageCode);

            $locStruct = $locationService->newLocationCreateStruct((int) $params['parent_location_id']);

            $draft = $contentService->createContent($createStruct, [$locStruct]);

            $published = $contentService->publishVersion($draft->versionInfo);
            $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

            return ToolResult::ok(
                sprintf('Created folder "%s" (ID: %d)', $params['name'], $published->id),
                [
                    'content_id' => $published->id,
                    'location_id' => $location->id,
                ],
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to create folder: %s', $e->getMessage()));
        }
    }
}
