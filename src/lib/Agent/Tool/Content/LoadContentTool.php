<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Masilia\AiAssistant\Field\FieldValueStringifierRegistry;
use Psr\Log\LoggerInterface;

readonly class LoadContentTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
        private FieldValueStringifierRegistry $stringifierRegistry,
    ) {

    }

    public function getName(): string
    {
        return ToolName::LOAD_CONTENT;
    }

    public function getDescription(): string
    {
        return 'Load content by ID, remote ID, or location ID. Returns content metadata and field values.';
    }

    public function getParameters(): array
    {
        $availableLanguages = array_map(
            static fn($lang) => $lang->languageCode,
            $this->repository->getContentLanguageService()->loadLanguages(),
        );

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
                    'description' => sprintf(
                        'Language code (defaults to content main language). Available: %s',
                        implode(', ', $availableLanguages),
                    ),
                ],
            ],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentService = $this->repository->getContentService();
            $requestedLanguage = $params['language'] ?? null;

            // When a specific language is requested, load in that language.
            // Otherwise, load without language restriction (loads the main
            // translation) and use the content's mainLanguageCode for field
            // extraction — this prevents empty fields when content isn't in
            // the repository's default language (e.g. eng-GB).
            $languages = $requestedLanguage !== null ? [$requestedLanguage] : null;

            if (isset($params['content_id'])) {
                $content = $contentService->loadContent((int) $params['content_id'], $languages);
            } elseif (isset($params['remote_id'])) {
                $content = $contentService->loadContentByRemoteId($params['remote_id'], $languages);
            } elseif (isset($params['location_id'])) {
                $locationService = $this->repository->getLocationService();
                $location = $locationService->loadLocation((int) $params['location_id']);
                $content = $contentService->loadContent($location->contentId, $languages);
            } else {
                throw new \InvalidArgumentException('Must provide content_id, remote_id, or location_id');
            }

            $languageCode = $requestedLanguage ?? $content->contentInfo->mainLanguageCode;

            $fields = [];
            foreach ($content->getFieldsByLanguage($languageCode) as $field) {
                $fieldDef = $content->getContentType()->getFieldDefinition($field->fieldDefIdentifier);
                $fields[$field->identifier] = $this->stringifierRegistry->toString($field, $fieldDef);
            }

            return ToolResult::ok(
                sprintf('Loaded content: %s (ID: %d)', $content->contentInfo->name ?? 'unnamed', $content->id),
                [
                    'content_id' => $content->id,
                    'content_type' => $content->contentInfo->contentTypeId,
                    'name' => $content->contentInfo->name,
                    'remote_id' => $content->contentInfo->remoteId,
                    'main_location_id' => $content->contentInfo->mainLocationId,
                    'fields' => $fields,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'load content');
        }
    }
}
