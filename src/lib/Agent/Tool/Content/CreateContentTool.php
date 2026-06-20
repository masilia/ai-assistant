<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ContentCreator;
use Masilia\AiAssistant\Agent\Tool\SiteaccessLocationResolver;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\AiConstants;
use Psr\Log\LoggerInterface;

readonly class CreateContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ContentCreator $contentCreator,
        private SiteaccessLocationResolver $locationResolver,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::CREATE_CONTENT;
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
                    'default' => AiConstants::DEFAULT_LANGUAGE_CODE,
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
            $explicitId = isset($params['parent_location_id']) ? (int) $params['parent_location_id'] : null;
            $parentLocationId = $this->locationResolver->resolve($params['siteaccess'] ?? '', $explicitId);

            if ($parentLocationId === null) {
                return ToolResult::error('Provide either parent_location_id or a siteaccess name to resolve the parent location.');
            }

            $result = $this->contentCreator->createAndPublish(
                $contentTypeIdentifier,
                [$parentLocationId],
                $attributes,
                $languageCode,
                $remoteId,
                $locationRemoteId,
            );

            return ToolResult::ok(
                sprintf('Created %s (ID: %d)', $contentTypeIdentifier, $result['content']->id),
                [
                    'content_id' => $result['content']->id,
                    'location_id' => $result['location']->id,
                    'remote_id' => $result['content']->contentInfo->remoteId,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'create content');
        }
    }
}
