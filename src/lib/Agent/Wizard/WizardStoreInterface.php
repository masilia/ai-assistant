<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Wizard;

/**
 * Persists {@see WizardState} across requests within a user session.
 */
interface WizardStoreInterface
{
    public function get(int $userId): ?WizardState;

    public function put(int $userId, WizardState $state): void;

    public function clear(int $userId): void;
}
