<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class LoadContentTool implements ToolInterface
{
    private string $defaultLanguageCode;

    public function __construct(
        private Repository $repository,
    ) {
        $this->defaultLanguageCode = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
    }

    public function getName(): string
    {
        return 'load_content';
    }

    public function getDescription(): string
    {
        return 'Load content by ID, remote ID, or location ID. Returns content metadata and field values.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_id' => [
                    'type' => 'integer',
                    'description' => 'Content ID to load',
                ],
                'remote_id' => [
                    'type' => 'string',
                    'description' => 'Remote ID to load',
                ],
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'Location ID to load content from',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => "Language code (default: $this->defaultLanguageCode)",
                    'default' => $this->defaultLanguageCode,
                ],
            ],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $languageCode = $params['language'] ?? $this->defaultLanguageCode;

            $content = $this->repository->sudo(function () use ($params, $languageCode) {
                $contentService = $this->repository->getContentService();

                if (isset($params['content_id'])) {
                    return $contentService->loadContent((int) $params['content_id'], [$languageCode]);
                }

                if (isset($params['remote_id'])) {
                    return $contentService->loadContentByRemoteId($params['remote_id'], [$languageCode]);
                }

                if (isset($params['location_id'])) {
                    $locationService = $this->repository->getLocationService();
                    $location = $locationService->loadLocation((int) $params['location_id']);

                    return $contentService->loadContent($location->contentId, [$languageCode]);
                }

                throw new \InvalidArgumentException('Must provide content_id, remote_id, or location_id');
            });

            $fields = [];
            foreach ($content->fields as $field) {
                $fields[$field->identifier] = $field->value?->text ?? $field->value?->toHash() ?? null;
            }

            return ToolResult::ok(
                sprintf('Loaded content: %s (ID: %d)', $content->contentInfo->name ?? 'unnamed', $content->id),
                [
                    'content_id' => $content->id,
                    'content_type' => $content->contentInfo->contentTypeId,
                    'name' => $content->contentInfo->name,
                    'remote_id' => $content->remoteId,
                    'main_location_id' => $content->contentInfo->mainLocationId,
                    'fields' => $fields,
                ],
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to load content: %s', $e->getMessage()));
        }
    }
}
