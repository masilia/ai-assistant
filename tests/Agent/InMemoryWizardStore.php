<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent;

use Masilia\AiAssistant\Agent\Wizard\WizardState;
use Masilia\AiAssistant\Agent\Wizard\WizardStoreInterface;

/**
 * In-memory wizard store for testing.
 */
final class InMemoryWizardStore implements WizardStoreInterface
{
    /** @var array<int, WizardState> */
    private array $store = [];

    public function get(int $userId): ?WizardState
    {
        return $this->store[$userId] ?? null;
    }

    public function put(int $userId, WizardState $state): void
    {
        $this->store[$userId] = $state;
    }

    public function clear(int $userId): void
    {
        unset($this->store[$userId]);
    }
}
