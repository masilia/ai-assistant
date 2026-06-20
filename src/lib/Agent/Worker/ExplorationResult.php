<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Worker;

/**
 * Output of the SiteExplorer worker — unified exploration snapshot.
 */
readonly class ExplorationResult
{
    /**
     * @param list<string>             $siteaccesses         All configured siteaccesses
     * @param string|null              $matchedSiteaccess    Fuzzy-matched siteaccess from user input (null = no match)
     * @param int|null                 $rootLocationId       Root location ID of the matched siteaccess
     * @param array<string, mixed>     $siteStructure        Browse result (children, etc.)
     * @param list<array<string, mixed>> $parentCandidates   Candidate parent locations
     * @param list<array<string, mixed>> $blockTypes         Available block types
     * @param list<string>             $parentBlocksAllowedTypes  Allowed block type identifiers on the parent page's "blocks" field
     * @param string|null              $errorMessage         If exploration failed
     */
    public function __construct(
        public array  $siteaccesses,
        public ?string $matchedSiteaccess,
        public ?int   $rootLocationId,
        public array  $siteStructure,
        public array  $parentCandidates,
        public array  $blockTypes,
        public array  $parentBlocksAllowedTypes = [],
        public ?string $errorMessage = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function hasMatchedSiteaccess(): bool
    {
        return $this->matchedSiteaccess !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'siteaccesses' => $this->siteaccesses,
            'matchedSiteaccess' => $this->matchedSiteaccess,
            'rootLocationId' => $this->rootLocationId,
            'siteStructure' => $this->siteStructure,
            'parentCandidates' => $this->parentCandidates,
            'blockTypes' => $this->blockTypes,
            'parentBlocksAllowedTypes' => $this->parentBlocksAllowedTypes,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
