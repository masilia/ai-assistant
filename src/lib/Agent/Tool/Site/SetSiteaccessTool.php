<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Site;

use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;

readonly class SetSiteaccessTool implements ToolInterface
{
    public function __construct(
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
        $name = $params['name'];

        return ToolResult::ok(
            sprintf('Siteaccess set to "%s". Future operations will target this site tree.', $name),
            [
                'name' => $name,
                'set' => true,
            ],
        );
    }
}
