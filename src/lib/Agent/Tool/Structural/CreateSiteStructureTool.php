<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Structural;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentCreator;
use Masilia\AiAssistant\Agent\Tool\ContentUpdater;
use Masilia\AiAssistant\Agent\Tool\ImageFileHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\AiConstants;
use Masilia\AiAssistant\Client\ImageGenerationClient;
use Masilia\AiAssistant\ContentTypeId;
use Masilia\AiAssistant\FieldId;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class CreateSiteStructureTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ConfigResolverInterface $configResolver,
        private ImageGenerationClient $imageClient,
        private ContentCreator $contentCreator,
        private ContentUpdater $contentUpdater,
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
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
                ],
            ],
            'required' => ['site_name', 'domain', 'pages'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        $tempFiles = [];

        try {
            $languageCode = $params['language']
                ?? $this->repository->getContentLanguageService()->getDefaultLanguageCode();
            $siteName = $params['site_name'];
            $domain = $params['domain'];
            $description = $params['description'] ?? '';

            $config = $this->resolveSiteConfig();

            // 1-3. Create site skeleton (container + layout + home page)
            [$sitePublished, $siteLocation, $homePublished, $homeLocation, $layoutPublished]
                = $this->createSiteSkeleton(
                    $config['siteTypeId'],
                    $config['layoutTypeId'],
                    $config['homePageTypeId'],
                    $siteName,
                    $domain,
                    $description,
                    $languageCode,
                );

            // 4. Create media folder structure
            $mediaFolder = $this->createMediaFolderStructure(
                $config['folderTypeId'],
                $config['mediaRootLocationId'],
                $siteName,
                $languageCode,
            );

            // 6. Create pages under home page
            $createdPages = $this->createPagesRecursive(
                $params['pages'] ?? [],
                $homeLocation->id,
                $languageCode,
            );

            // 5. Pre-generate site images (after commit — updates content)
            $tempFiles = $this->preGenerateSiteImages($siteName, $sitePublished->id, $languageCode);

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
        } catch (Throwable $e) {
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
     * Resolve content type identifiers and media root from config.
     *
     * @return array{siteTypeId: string, homePageTypeId: string, layoutTypeId: string, folderTypeId: string, mediaRootLocationId: int}
     */
    private function resolveSiteConfig(): array
    {
        return [
            'siteTypeId' => $this->resolveConfig('site_content_type', ContentTypeId::SITE),
            'homePageTypeId' => $this->resolveConfig('home_page_content_type', ContentTypeId::HOME_PAGE),
            'layoutTypeId' => $this->resolveConfig('layout_content_type', ContentTypeId::LAYOUT),
            'folderTypeId' => $this->resolveConfig('folder_content_type', ContentTypeId::FOLDER),
            'mediaRootLocationId' => (int) $this->resolveConfig('media_root_location_id', AiConstants::MEDIA_ROOT_LOCATION_ID),
        ];
    }

    /**
     * Create the media folder and its standard sub-folders.
     *
     * @return array{content_id: int, location_id: int}
     */
    private function createMediaFolderStructure(
        string $folderTypeId,
        int $mediaRootLocationId,
        string $siteName,
        string $languageCode,
    ): array {
        $mediaFolder = $this->createFolder($folderTypeId, $languageCode, $mediaRootLocationId, sprintf('%s Media', $siteName));
        $this->createFolder($folderTypeId, $languageCode, $mediaFolder['location_id'], 'Images');
        $this->createFolder($folderTypeId, $languageCode, $mediaFolder['location_id'], 'Files');
        $this->createFolder($folderTypeId, $languageCode, $mediaFolder['location_id'], 'Multimedia');

        return $mediaFolder;
    }

    /**
     * Create the site container, layout config, and home page.
     */
    private function createSiteSkeleton(
        string $siteTypeId,
        string $layoutTypeId,
        string $homePageTypeId,
        string $siteName,
        string $domain,
        string $description,
        string $languageCode,
    ): array {
        $siteResult = $this->contentCreator->createAndPublish(
            $siteTypeId,
            [AiConstants::ROOT_LOCATION_ID],
            [
                FieldId::TITLE => $siteName,
                FieldId::DOMAIN => $domain,
                FieldId::DESCRIPTION => $description,
            ],
            $languageCode,
        );
        $sitePublished = $siteResult['content'];
        $siteLocation = $siteResult['location'];

        $layoutResult = $this->contentCreator->createAndPublish(
            $layoutTypeId,
            [$siteLocation->id],
            [FieldId::BO_TITLE => sprintf('%s Layout Configuration', $siteName)],
            $languageCode,
        );
        $layoutPublished = $layoutResult['content'];

        $homeResult = $this->contentCreator->createAndPublish(
            $homePageTypeId,
            [$siteLocation->id],
            [
                FieldId::TITLE => 'Home',
                FieldId::BLOCKS => [],
            ],
            $languageCode,
        );
        $homePublished = $homeResult['content'];
        $homeLocation = $homeResult['location'];

        return [$sitePublished, $siteLocation, $homePublished, $homeLocation, $layoutPublished];
    }

    private function resolveConfig(string $key, mixed $default): mixed
    {
        try {
            return $this->configResolver->getParameter($key, AiConstants::CONFIG_NAMESPACE);
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @return array{content_id: int, location_id: int}
     */
    private function createFolder(
        string $folderTypeId,
        string $languageCode,
        int $parentLocationId,
        string $name,
    ): array {
        [$content, $location] = $this->contentCreator->createAndPublish(
            $folderTypeId,
            [$parentLocationId],
            [FieldId::NAME => $name],
            $languageCode,
        );

        return [
            'content_id' => $content->id,
            'location_id' => $location->id,
        ];
    }

    private function createPagesRecursive(
        array $pages,
        int $parentLocationId,
        string $languageCode,
    ): array {
        $pageTypeId = $this->resolveConfig('page_content_type', ContentTypeId::PAGE);

        $created = [];

        foreach ($pages as $pageData) {
            $title = $pageData['title'] ?? 'Untitled';
            $pageDescription = $pageData['description'] ?? '';

            $result = $this->contentCreator->createAndPublish(
                $pageTypeId,
                [$parentLocationId],
                [
                    FieldId::TITLE => $title,
                    FieldId::DESCRIPTION => $pageDescription,
                    FieldId::BLOCKS => [],
                ],
                $languageCode,
            );
            $published = $result['content'];

            $pageEntry = [
                'content_id' => $published->id,
                'location_id' => $result['location']->id,
                'title' => $title,
            ];

            // Recurse for children
            if (!empty($pageData['children'])) {
                $pageEntry['children'] = $this->createPagesRecursive(
                    $pageData['children'],
                    $result['location']->id,
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
        if (!$this->imageClient->isConfigured()) {
            $this->aiLogger->info(
                'Image generation provider not configured for current siteaccess; skipping site favicon pre-generation.',
            );

            return [];
        }

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
            $this->contentUpdater->updateFields(
                $siteContentId,
                [FieldId::FAVICON => $faviconPath],
                $languageCode,
            );
        } catch (Throwable $e) {
            $this->aiLogger->warning(sprintf(
                'Failed to pre-generate site images for "%s": %s',
                $siteName,
                $e->getMessage(),
            ));
        }

        return $tempFiles;
    }
}
