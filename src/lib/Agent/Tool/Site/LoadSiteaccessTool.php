<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Agent\Tool\AgentErrorHelper;
use Masilia\AiAssistant\Agent\Tool\ToolInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;

readonly class LoadSiteaccessTool implements ToolInterface
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return ToolName::LOAD_SITEACCESS;
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
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Siteaccess name',
                ],
            ],
            'required' => ['siteaccess'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $siteaccess = $params['siteaccess'];

            // Load siteaccess config from Ibexa
            $rootLocationId = $this->configResolver->getParameter('content.tree_root.location_id', scope: $siteaccess);

            return ToolResult::ok(
                sprintf('Loaded siteaccess "%s"', $siteaccess),
                [
                    'siteaccess' => $siteaccess,
                    'root_location_id' => $rootLocationId,
                ],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'load siteaccess');
        }
    }
}
