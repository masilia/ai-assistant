<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Entity;

use Masilia\Bundle\AiAssistant\Repository\AiRequestLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Telemetry record for one AI API call.
 *
 * Stores no PII (no field content, no user prompt, no API key).
 * Used by the Usage tab in the admin dashboard to show request
 * volume, success rate, average latency, and per-provider usage.
 *
 * Old rows are not auto-pruned — a future migration can add
 * a cron job (or this can be done in the host app's housekeeping
 * command) to drop rows older than N days.
 */
#[ORM\Entity(repositoryClass: AiRequestLogRepository::class)]
#[ORM\Table(name: 'app_ai_request_log')]
#[ORM\Index(name: 'idx_ai_log_created', columns: ['createdAt'])]
#[ORM\Index(name: 'idx_ai_log_provider', columns: ['providerIdentifier'])]
class AiRequestLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $providerIdentifier;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $modelIdentifier;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $siteaccess = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $success;

    #[ORM\Column(type: Types::INTEGER)]
    private int $latencyMs;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tokensIn = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tokensOut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProviderIdentifier(): string { return $this->providerIdentifier; }
    public function getModelIdentifier(): string { return $this->modelIdentifier; }
    public function getSiteaccess(): ?string { return $this->siteaccess; }
    public function isSuccess(): bool { return $this->success; }
    public function getLatencyMs(): int { return $this->latencyMs; }
    public function getErrorCode(): ?string { return $this->errorCode; }
    public function getTokensIn(): ?int { return $this->tokensIn; }
    public function getTokensOut(): ?int { return $this->tokensOut; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setProviderIdentifier(string $v): self { $this->providerIdentifier = $v; return $this; }
    public function setModelIdentifier(string $v): self { $this->modelIdentifier = $v; return $this; }
    public function setSiteaccess(?string $v): self { $this->siteaccess = $v; return $this; }
    public function setSuccess(bool $v): self { $this->success = $v; return $this; }
    public function setLatencyMs(int $v): self { $this->latencyMs = $v; return $this; }
    public function setErrorCode(?string $v): self { $this->errorCode = $v; return $this; }
    public function setTokensIn(?int $v): self { $this->tokensIn = $v; return $this; }
    public function setTokensOut(?int $v): self { $this->tokensOut = $v; return $this; }
}
