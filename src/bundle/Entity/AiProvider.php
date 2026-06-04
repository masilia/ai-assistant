<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Entity;

use Masilia\Bundle\AiAssistant\Repository\AiProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiProviderRepository::class)]
#[ORM\Table(name: 'app_ai_provider')]
class AiProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $identifier;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $apiUrl = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    /**
     * @var Collection<int, AiModel>
     */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: AiModel::class, cascade: ['all'], orphanRemoval: true)]
    private Collection $models;

    public function __construct()
    {
        $this->models = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(?string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;
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

    /**
     * @return Collection<int, AiModel>
     */
    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(AiModel $model): self
    {
        if (!$this->models->contains($model)) {
            $this->models->add($model);
            $model->setProvider($this);
        }
        return $this;
    }

    public function removeModel(AiModel $model): self
    {
        $this->models->removeElement($model);

        return $this;
    }
}
