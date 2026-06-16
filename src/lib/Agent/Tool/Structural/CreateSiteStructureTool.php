<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ImageFileHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\ContentTypeId;
use Masilia\AiAssistant\FieldId;
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
        return ToolName::CREATE_SITE_STRUCTURE;
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
            $contentTypeService = $this->repository->getContentTypeService();

            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();
            $siteName = $params['site_name'];
            $domain = $params['domain'];
            $description = $params['description'] ?? '';

            // Resolve content type identifiers from config
            $siteContentTypeId = $this->resolveConfig('site_content_type', ContentTypeId::SITE);
            $homePageContentTypeId = $this->resolveConfig('home_page_content_type', ContentTypeId::HOME_PAGE);
            $layoutContentTypeId = $this->resolveConfig('layout_content_type', ContentTypeId::LAYOUT);
            $folderContentTypeId = $this->resolveConfig('folder_content_type', ContentTypeId::FOLDER);
            $mediaRootLocationId = (int) $this->resolveConfig('media_root_location_id', 43);

            $siteType = $contentTypeService->loadContentTypeByIdentifier($siteContentTypeId);
            $homePageType = $contentTypeService->loadContentTypeByIdentifier($homePageContentTypeId);
            $layoutType = $contentTypeService->loadContentTypeByIdentifier($layoutContentTypeId);
            $folderType = $contentTypeService->loadContentTypeByIdentifier($folderContentTypeId);

            // 1-3. Create site skeleton (container + layout + home page)
            [$sitePublished, $siteLocation, $homePublished, $homeLocation, $layoutPublished]
                = $this->createSiteSkeleton($siteType, $layoutType, $homePageType, $siteName, $domain, $description, $languageCode);

            // 4. Create media folder structure
            $mediaFolder = $this->createFolder($folderType, $languageCode, $mediaRootLocationId, sprintf('%s Media', $siteName));
            $this->createFolder($folderType, $languageCode, $mediaFolder['location_id'], 'Images');
            $this->createFolder($folderType, $languageCode, $mediaFolder['location_id'], 'Files');
            $this->createFolder($folderType, $languageCode, $mediaFolder['location_id'], 'Multimedia');

            // 5. Pre-generate site images
            $tempFiles = $this->preGenerateSiteImages($siteName, $sitePublished->id, $languageCode);

            // 6. Create pages under home page
            $createdPages = $this->createPagesRecursive(
                $params['pages'] ?? [],
                $homeLocation->id,
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
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'create site structure');
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Create the site container, layout config, and home page.
     *
     * @return array{0: Content, 1: Location, 2: Content, 3: Location, 4: Content}
     */
    private function createSiteSkeleton(
        ContentType $siteType,
        ContentType $layoutType,
        ContentType $homePageType,
        string $siteName,
        string $domain,
        string $description,
        string $languageCode,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        // 1. Create site container under location 2
        $siteCreateStruct = $contentService->newContentCreateStruct($siteType, $languageCode);
        $siteCreateStruct->setField(FieldId::TITLE, $siteName, $languageCode);
        $siteCreateStruct->setField(FieldId::DOMAIN, $domain, $languageCode);
        $siteCreateStruct->setField(FieldId::DESCRIPTION, $description, $languageCode);

        $siteLocStruct = $locationService->newLocationCreateStruct(2);
        $siteDraft = $contentService->createContent($siteCreateStruct, [$siteLocStruct]);
        $sitePublished = $contentService->publishVersion($siteDraft->versionInfo);
        $siteLocation = $locationService->loadLocation($sitePublished->contentInfo->mainLocationId);

        // 2. Create layout config under site
        $layoutCreateStruct = $contentService->newContentCreateStruct($layoutType, $languageCode);
        $layoutCreateStruct->setField(FieldId::BO_TITLE, sprintf('%s Layout Configuration', $siteName), $languageCode);

        $layoutLocStruct = $locationService->newLocationCreateStruct($siteLocation->id);
        $layoutDraft = $contentService->createContent($layoutCreateStruct, [$layoutLocStruct]);
        $layoutPublished = $contentService->publishVersion($layoutDraft->versionInfo);

        // 3. Create home page under site
        $homeCreateStruct = $contentService->newContentCreateStruct($homePageType, $languageCode);
        $homeCreateStruct->setField(FieldId::TITLE, 'Home', $languageCode);
        $homeCreateStruct->setField(FieldId::BLOCKS, [], $languageCode);

        $homeLocStruct = $locationService->newLocationCreateStruct($siteLocation->id);
        $homeDraft = $contentService->createContent($homeCreateStruct, [$homeLocStruct]);
        $homePublished = $contentService->publishVersion($homeDraft->versionInfo);
        $homeLocation = $locationService->loadLocation($homePublished->contentInfo->mainLocationId);

        return [$sitePublished, $siteLocation, $homePublished, $homeLocation, $layoutPublished];
    }

    private function resolveConfig(string $key, mixed $default): mixed
    {
        try {
            return $this->configResolver->getParameter($key, self::CONFIG_NAMESPACE);
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @return array{content_id: int, location_id: int}
     */
    private function createFolder(
        ContentType $folderType,
        string $languageCode,
        int $parentLocationId,
        string $name,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        $createStruct = $contentService->newContentCreateStruct($folderType, $languageCode);
        $createStruct->setField(FieldId::NAME, $name, $languageCode);

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
        string $languageCode,
    ): array {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $pageType = $contentTypeService->loadContentTypeByIdentifier(
            $this->resolveConfig('page_content_type', ContentTypeId::PAGE),
        );

        $created = [];

        foreach ($pages as $pageData) {
            $title = $pageData['title'] ?? 'Untitled';
            $pageDescription = $pageData['description'] ?? '';

            $createStruct = $contentService->newContentCreateStruct($pageType, $languageCode);
            $createStruct->setField(FieldId::TITLE, $title, $languageCode);
            $createStruct->setField(FieldId::DESCRIPTION, $pageDescription, $languageCode);
            $createStruct->setField(FieldId::BLOCKS, [], $languageCode);

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
                    $languageCode,
                );
            }

            $created[] = $pageEntry;
        }

        return $created;
    }

    /**
     * @return string[] Temp file paths created (for cleanup)
     */
    private function preGenerateSiteImages(
        string $siteName,
        int $siteContentId,
        string $languageCode,
    ): array {
        $contentService = $this->repository->getContentService();
        $tempFiles = [];

        try {
            // Generate favicon
            $faviconResult = $this->imageClient->generate(
                sprintf('Simple clean favicon icon for "%s" website, minimalist, professional', $siteName),
                '1:1',
            );
            $faviconPath = ImageFileHelper::saveTempFile($faviconResult->imageData, $faviconResult->mimeType, 'ai_site_');
            $tempFiles[] = $faviconPath;

            // Update site content with favicon
            $siteContentInfo = $contentService->loadContentInfo($siteContentId);

            $draft = $contentService->createContentDraft($siteContentInfo);
            $updateStruct = $contentService->newContentUpdateStruct();
            $updateStruct->initialLanguageCode = $languageCode;
            $updateStruct->setField(FieldId::FAVICON, $faviconPath, $languageCode);
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

}
