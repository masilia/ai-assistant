<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class SetSiteaccessTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'set_siteaccess';
    }

    public function getDescription(): string
    {
        return 'Set the current siteaccess for content operations.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Siteaccess name to set',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $name = $params['name'];

            return ToolResult::ok(
                sprintf('Siteaccess set to "%s". Future operations will target this site tree.', $name),
                [
                    'name' => $name,
                    'set' => true,
                ],
            );
        } catch (\Throwable $e) {
            return ToolResult::error(sprintf('Failed to set siteaccess: %s', $e->getMessage()));
        }
    }
}
