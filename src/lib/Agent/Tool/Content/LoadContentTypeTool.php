<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class LoadContentTypeTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'load_content_type';
    }

    public function getDescription(): string
    {
        return 'Load content type definition including all field definitions.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'identifier' => [
                    'type' => 'string',
                    'description' => 'Content type identifier (e.g., "article", "page")',
                ],
            ],
            'required' => ['identifier'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentType = $this->repository->getContentTypeService()
                ->loadContentTypeByIdentifier($params['identifier']);

            $fields = [];
            foreach ($contentType->fieldDefinitions as $fieldDef) {
                $fields[$fieldDef->identifier] = [
                    'type' => $fieldDef->fieldTypeIdentifier,
                    'name' => $fieldDef->name,
                    'required' => $fieldDef->isRequired,
                    'identifier' => $fieldDef->identifier,
                ];
            }

            return ToolResult::ok(
                sprintf('Loaded content type: %s', $contentType->identifier),
                [
                    'identifier' => $contentType->identifier,
                    'name' => $contentType->getName(),
                    'group' => $contentType->contentTypeGroup->identifier,
                    'is_container' => $contentType->isContainer,
                    'fields' => $fields,
                ],
            );
        } catch (UnauthorizedException $e) {
            return AgentErrorHelper::unauthorized('load content type');
        } catch (NotFoundException $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'load content type');
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'load content type');
        }
    }
}
