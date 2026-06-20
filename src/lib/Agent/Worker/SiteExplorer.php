<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\AiAssistant\Agent\Tool\ToolName;
use Masilia\AiAssistant\Agent\Tool\ToolRegistry;
use Masilia\AiAssistant\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Composite exploration worker.
 *
 * Solves the admin-vs-front-office problem:
 * - The user is in admin context (which is its own siteaccess)
 * - They want to manage front-office siteaccesses (e.g. "fossilexit")
 * - We must discover front-office siteaccesses, match user input to one,
 *   resolve its root location, then browse the subtree under that root.
 *
 * All four exploration steps are executed in a single worker call so the
 * orchestrator LLM only sees ONE tool call, not four separate calls.
 */
final readonly class SiteExplorer
{
    public function __construct(
        private SiteAccessServiceInterface $siteAccessService,
        private ConfigResolverInterface    $configResolver,
        private ToolRegistry               $toolRegistry,
        private LoggerInterface            $aiLogger,
    ) {
    }

    public function explore(string $siteaccess): ExplorationResult
    {
        try {
            $siteaccesses = $this->listSiteaccesses();
            $matched = $this->matchSiteaccess($siteaccess, $siteaccesses);
            $rootLocationId = null;
            $siteStructure = [];
            $parentCandidates = [];
            $blockTypes = [];

            if ($matched !== null) {
                $rootLocationId = $this->resolveRoot($matched);
                $siteStructure = $this->browse($matched);
                $parentCandidates = $this->findParents($matched);
                $blockTypes = $this->listBlocks();
            }

            return new ExplorationResult(
                siteaccesses: $siteaccesses,
                matchedSiteaccess: $matched,
                rootLocationId: $rootLocationId,
                siteStructure: $siteStructure,
                parentCandidates: $parentCandidates,
                blockTypes: $blockTypes,
            );
        } catch (Throwable $e) {
            $this->aiLogger->error('[SiteExplorer] Exploration failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new ExplorationResult(
                siteaccesses: $this->listSiteaccesses(),
                matchedSiteaccess: null,
                rootLocationId: null,
                siteStructure: [],
                parentCandidates: [],
                blockTypes: [],
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function listSiteaccesses(): array
    {
        $names = [];
        foreach ($this->siteAccessService->getAll() as $sa) {
            $names[] = $sa->name;
        }
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $siteaccesses
     */
    private function matchSiteaccess(string $input, array $siteaccesses): ?string
    {
        $needle = $this->normalize($input);

        // Exact match wins
        foreach ($siteaccesses as $name) {
            if ($this->normalize($name) === $needle) {
                return $name;
            }
        }

        // Substring containment — handles "fossil exit" → "fossilexit"
        foreach ($siteaccesses as $name) {
            if ($needle !== '' && str_contains($this->normalize($name), $needle)) {
                return $name;
            }
        }

        return null;
    }

    private function normalize(string $s): string
    {
        return strtolower(str_replace(['_', '-', ' '], '', $s));
    }

    private function resolveRoot(string $siteaccess): ?int
    {
        try {
            return (int) $this->configResolver->getParameter(
                'content.tree_root.location_id',
                scope: $siteaccess,
            );
        } catch (Throwable $e) {
            $this->aiLogger->warning('[SiteExplorer] Could not resolve root for {siteaccess}: {message}', [
                'siteaccess' => $siteaccess,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function browse(string $siteaccess): array
    {
        $tool = $this->toolRegistry->get(ToolName::BROWSE_SITE_STRUCTURE);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([
            'siteaccess' => $siteaccess,
            'depth' => 2,
        ]);

        return $result->success ? $result->data : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findParents(string $siteaccess): array
    {
        $tool = $this->toolRegistry->get(ToolName::FIND_PARENT_CANDIDATES);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([
            'content_type' => 'page',
            'siteaccess' => $siteaccess,
        ]);

        return $result->success ? ($result->data['candidates'] ?? []) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listBlocks(): array
    {
        $tool = $this->toolRegistry->get(ToolName::LIST_BLOCKS);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([]);

        return $result->success ? ($result->data['blocks'] ?? []) : [];
    }
}
