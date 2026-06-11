<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Health;

/**
 * 3-state health of the AI engine, surfaced to the admin dashboard banner.
 *
 * The string values are the wire format the React SPA expects
 * (see `banner` state machine in `ai-settings/components/`).
 */
enum HealthState: string
{
    case NotConfigured = 'not_configured';
    case Online        = 'online';
    case Offline       = 'offline';
}
