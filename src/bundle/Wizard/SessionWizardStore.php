<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Wizard;

use Masilia\AiAssistant\Agent\Wizard\WizardState;
use Masilia\AiAssistant\Agent\Wizard\WizardStoreInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Session-backed wizard store.
 *
 * Wizard state is serialized into the Symfony session and automatically
 * cleared when the user logs out (session invalidation).
 */
final readonly class SessionWizardStore implements WizardStoreInterface
{
    private const SESSION_KEY = '_ai_assistant/wizard';

    public function __construct(
        private SessionInterface $session,
    ) {
    }

    public function get(int $userId): ?WizardState
    {
        $data = $this->session->get(self::SESSION_KEY . '/' . $userId);

        return $data instanceof WizardState ? $data : null;
    }

    public function put(int $userId, WizardState $state): void
    {
        $this->session->set(self::SESSION_KEY . '/' . $userId, $state);
    }

    public function clear(int $userId): void
    {
        $this->session->remove(self::SESSION_KEY . '/' . $userId);
    }
}
