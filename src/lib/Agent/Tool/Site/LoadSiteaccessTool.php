<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class LoadSiteaccessTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private ConfigResolverInterface $configResolver,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'load_siteaccess';
    }

    public function getDescription(): string
    {
        return 'Load siteaccess configuration including root location ID.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Siteaccess name',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $name = $params['name'];

            // Load siteaccess config from Ibexa
            $rootLocationId = $this->configResolver->getParameter('content.tree_root.location_id', scope: $name);

            return ToolResult::ok(
                sprintf('Loaded siteaccess "%s"', $name),
                [
                    'name' => $name,
                    'root_location_id' => $rootLocationId,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::logAndReturn($this->aiLogger, $e, 'load siteaccess');
        }
    }
}
