<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Psr\Log\LoggerInterface;

readonly class CreateSiteStructureTool implements ToolInterface
{
    private const CONFIG_NAMESPACE = 'masilia_ai_assistant';

    public function __construct(
        private Repository $repository,
        private ConfigResolverInterface $configResolver,
        private ImageGenerationClient $imageClient,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'create_site_structure';
    }

    public function getDescription(): string
    {
        return 'Create an entire site skeleton: site container, layout, home page, media folders, and pages.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'site_name' => [
                    'type' => 'string',
                    'description' => 'Site name (e.g. "Fossil Exit")',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Domain (e.g. "fossilexit.org")',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Site description',
                ],
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Target siteaccess for config resolution',
                ],
                'pages' => [
                    'type' => 'array',
                    'description' => 'Page structure to create under home page',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'children' => ['type' => 'array'],
                        ],
                    ],
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code (default: eng-GB)',
                    'default' => 'eng-GB',
                ],
            ],
            'required' => ['site_name', 'domain', 'pages'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        $tempFiles = [];

        try {
            $contentService = $this->repository->getContentService();
            $locationService = $this->repository->getLocationService();
            $contentTypeService = $this->repository->getContentTypeService();

            $languageCode = $params['language'] ?? 'eng-GB';
            $siteName = $params['site_name'];
            $domain = $params['domain'];
            $description = $params['description'] ?? '';

            // Resolve content type identifiers from config
            $siteContentTypeId = $this->resolveConfig('site_content_type', 'site');
            $homePageContentTypeId = $this->resolveConfig('home_page_content_type', 'home_page');
            $layoutContentTypeId = $this->resolveConfig('layout_content_type', 'layout_config');
            $folderContentTypeId = $this->resolveConfig('folder_content_type', 'folder');
            $mediaRootLocationId = (int) $this->resolveConfig('media_root_location_id', 43);

            $siteType = $contentTypeService->loadContentTypeByIdentifier($siteContentTypeId);
            $homePageType = $contentTypeService->loadContentTypeByIdentifier($homePageContentTypeId);
            $layoutType = $contentTypeService->loadContentTypeByIdentifier($layoutContentTypeId);
            $folderType = $contentTypeService->loadContentTypeByIdentifier($folderContentTypeId);

            $createdPages = [];

            // 1. Create site container under location 2
            $siteCreateStruct = $contentService->newContentCreateStruct($siteType, $languageCode);
            $siteCreateStruct->setField('title', $siteName, $languageCode);
            $siteCreateStruct->setField('domain', $domain, $languageCode);
            $siteCreateStruct->setField('description', $description, $languageCode);

            $siteLocStruct = $locationService->newLocationCreateStruct(2);
            $siteDraft = $contentService->createContent($siteCreateStruct, [$siteLocStruct]);
            $sitePublished = $contentService->publishVersion($siteDraft->versionInfo);
            $siteLocation = $locationService->loadLocation($sitePublished->contentInfo->mainLocationId);

            // 2. Create layout config under site
            $layoutCreateStruct = $contentService->newContentCreateStruct($layoutType, $languageCode);
            $layoutCreateStruct->setField('bo_title', sprintf('%s Layout Configuration', $siteName), $languageCode);

            $layoutLocStruct = $locationService->newLocationCreateStruct($siteLocation->id);
            $layoutDraft = $contentService->createContent($layoutCreateStruct, [$layoutLocStruct]);
            $layoutPublished = $contentService->publishVersion($layoutDraft->versionInfo);

            // 3. Create home page under site
            $homeCreateStruct = $contentService->newContentCreateStruct($homePageType, $languageCode);
            $homeCreateStruct->setField('title', 'Home', $languageCode);
            $homeCreateStruct->setField('blocks', [], $languageCode);

            $homeLocStruct = $locationService->newLocationCreateStruct($siteLocation->id);
            $homeDraft = $contentService->createContent($homeCreateStruct, [$homeLocStruct]);
            $homePublished = $contentService->publishVersion($homeDraft->versionInfo);
            $homeLocation = $locationService->loadLocation($homePublished->contentInfo->mainLocationId);

            // 4. Create media folder structure
            $mediaFolder = $this->createFolder($contentTypeService, $contentService, $locationService, $folderType, $languageCode, $mediaRootLocationId, sprintf('%s Media', $siteName));

            $imageFolder = $this->createFolder($contentTypeService, $contentService, $locationService, $folderType, $languageCode, $mediaFolder['location_id'], 'Images');
            $fileFolder = $this->createFolder($contentTypeService, $contentService, $locationService, $folderType, $languageCode, $mediaFolder['location_id'], 'Files');
            $multimediaFolder = $this->createFolder($contentTypeService, $contentService, $locationService, $folderType, $languageCode, $mediaFolder['location_id'], 'Multimedia');

            // 5. Pre-generate site images
            $tempFiles = $this->preGenerateSiteImages($siteName, $sitePublished->id, $contentService, $locationService, $contentTypeService, $siteType, $languageCode);

            // 6. Create pages under home page
            $createdPages = $this->createPagesRecursive(
                $params['pages'] ?? [],
                $homeLocation->id,
                $contentTypeService,
                $contentService,
                $locationService,
                $languageCode,
            );

            return ToolResult::ok(
                sprintf('Created site "%s" with %d pages', $siteName, count($createdPages)),
                [
                    'site_id' => $sitePublished->id,
                    'site_location_id' => $siteLocation->id,
                    'home_page_id' => $homePublished->id,
                    'home_page_location_id' => $homeLocation->id,
                    'layout_id' => $layoutPublished->id,
                    'media_folder_id' => $mediaFolder['content_id'],
                    'pages' => $createdPages,
                ],
            );
        } catch (ContentFieldValidationException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create site structure');
        } catch (BadStateException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create site structure');
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('create site structure');
        } catch (NotFoundException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create site structure');
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'create site structure');
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function resolveConfig(string $key, mixed $default): mixed
    {
        try {
            return $this->configResolver->getParameter($key, self::CONFIG_NAMESPACE);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function createFolder(
        $contentTypeService,
        $contentService,
        $locationService,
        $folderType,
        string $languageCode,
        int $parentLocationId,
        string $name,
    ): array {
        $createStruct = $contentService->newContentCreateStruct($folderType, $languageCode);
        $createStruct->setField('name', $name, $languageCode);

        $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
        $draft = $contentService->createContent($createStruct, [$locStruct]);
        $published = $contentService->publishVersion($draft->versionInfo);
        $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

        return [
            'content_id' => $published->id,
            'location_id' => $location->id,
        ];
    }

    private function createPagesRecursive(
        array $pages,
        int $parentLocationId,
        $contentTypeService,
        $contentService,
        $locationService,
        string $languageCode,
    ): array {
        $pageType = $contentTypeService->loadContentTypeByIdentifier(
            $this->resolveConfig('page_content_type', 'page'),
        );

        $created = [];

        foreach ($pages as $pageData) {
            $title = $pageData['title'] ?? 'Untitled';
            $pageDescription = $pageData['description'] ?? '';

            $createStruct = $contentService->newContentCreateStruct($pageType, $languageCode);
            $createStruct->setField('title', $title, $languageCode);
            $createStruct->setField('description', $pageDescription, $languageCode);
            $createStruct->setField('blocks', [], $languageCode);

            $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
            $draft = $contentService->createContent($createStruct, [$locStruct]);
            $published = $contentService->publishVersion($draft->versionInfo);
            $location = $locationService->loadLocation($published->contentInfo->mainLocationId);

            $pageEntry = [
                'content_id' => $published->id,
                'location_id' => $location->id,
                'title' => $title,
            ];

            // Recurse for children
            if (!empty($pageData['children'])) {
                $pageEntry['children'] = $this->createPagesRecursive(
                    $pageData['children'],
                    $location->id,
                    $contentTypeService,
                    $contentService,
                    $locationService,
                    $languageCode,
                );
            }

            $created[] = $pageEntry;
        }

        return $created;
    }

    private function preGenerateSiteImages(
        string $siteName,
        int $siteContentId,
        $contentService,
        $locationService,
        $contentTypeService,
        $siteType,
        string $languageCode,
    ): array {
        $tempFiles = [];

        try {
            // Generate favicon
            $faviconResult = $this->imageClient->generate(
                sprintf('Simple clean favicon icon for "%s" website, minimalist, professional', $siteName),
                '1:1',
            );
            $faviconPath = $this->saveTempFile($faviconResult->imageData, $faviconResult->mimeType);
            $tempFiles[] = $faviconPath;

            // Update site content with favicon
            $draft = $contentService->createContentDraft($siteType->getContentTypeInfo($siteContentId));
            $updateStruct = $contentService->newContentUpdateStruct();
            $updateStruct->languageCode = $languageCode;
            $updateStruct->setField('favicon', $faviconPath, $languageCode);
            $contentService->updateContent($draft->versionInfo, $updateStruct);
            $contentService->publishVersion($draft->versionInfo);
        } catch (\Throwable $e) {
            $this->aiLogger->warning(sprintf(
                'Failed to pre-generate site images for "%s": %s',
                $siteName,
                $e->getMessage(),
            ));
        }

        return $tempFiles;
    }

    private function saveTempFile(string $imageData, string $mimeType): string
    {
        $ext = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = tempnam(sys_get_temp_dir(), 'ai_site_') . '.' . $ext;
        $decoded = base64_decode($imageData, true);

        if ($decoded === false) {
            throw new \RuntimeException('Failed to decode image data');
        }

        if (file_put_contents($path, $decoded) === false) {
            throw new \RuntimeException('Failed to save generated image');
        }

        return $path;
    }
}
