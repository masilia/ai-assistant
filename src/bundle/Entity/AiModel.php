<?php

namespace Masilia\AiAssistant\Entity;

use Masilia\AiAssistant\Repository\AiModelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiModelRepository::class)]
#[ORM\Table(name: 'app_ai_model')]
class AiModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AiProvider::class, inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AiProvider $provider = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $identifier;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    #[ORM\Column(type: Types::FLOAT)]
    private float $temperature = 0.7;

    #[ORM\Column(type: Types::INTEGER)]
    private int $maxTokens = 2048;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?AiProvider
    {
        return $this->provider;
    }

    public function setProvider(?AiProvider $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }
}
