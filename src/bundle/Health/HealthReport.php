<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Health;

/**
 * Result of a single health probe. Immutable.
 *
 * Note: the previous wire shape declared a `providerId` field that was
 * always `null` (the lib-layer {@see \Masilia\AiAssistant\Client\Resolved\ResolvedProvider}
 * is framework-agnostic and carries no DB id). That field has been
 * removed; the dashboard already knows the active provider id from
 * the separate GET /admin/ai/settings/api/data endpoint.
 */
final readonly class HealthReport
{
    public function __construct(
        public HealthState $state,
        public ?string     $providerName = null,
        public ?string     $message      = null,
        public ?\DateTimeImmutable $checkedAt = null,
    ) {
    }

    public static function notConfigured(?\DateTimeImmutable $checkedAt = null): self
    {
        return new self(HealthState::NotConfigured, null, null, $checkedAt);
    }

    public static function online(string $providerName, ?string $message = null, ?\DateTimeImmutable $checkedAt = null): self
    {
        return new self(HealthState::Online, $providerName, $message, $checkedAt);
    }

    public static function offline(string $providerName, string $message, ?\DateTimeImmutable $checkedAt = null): self
    {
        return new self(HealthState::Offline, $providerName, $message, $checkedAt);
    }

    /**
     * Wire format the React SPA expects.
     *
     * @return array{state: string, providerName: ?string, message: ?string, checkedAt: string}
     */
    public function toArray(): array
    {
        return [
            'state'        => $this->state->value,
            'providerName' => $this->providerName,
            'message'      => $this->message,
            'checkedAt'    => ($this->checkedAt ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
