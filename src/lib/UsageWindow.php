<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

/**
 * Pre-defined time windows for the AI usage dashboard.
 */
enum UsageWindow: string
{
    case Last24Hours = '24h';
    case Last7Days   = '7d';
    case Last30Days  = '30d';

    public function modifier(): string
    {
        return match ($this) {
            self::Last24Hours => '-24 hours',
            self::Last7Days   => '-7 days',
            self::Last30Days  => '-30 days',
        };
    }
}
