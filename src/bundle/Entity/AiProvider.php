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

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $identifier;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $apiUrl = null;

    #[ORM\ManyToOne(targetEntity: AiModel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $activeChatModel = null;

    #[ORM\ManyToOne(targetEntity: AiModel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $activeImageModel = null;

    /**
     * @var Collection<int, AiModel>
     */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: AiModel::class, cascade: ['all'], orphanRemoval: true)]
    private Collection $models;

    /**
     * @var Collection<int, AiProviderSiteaccess>
     */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: AiProviderSiteaccess::class, cascade: ['all'], orphanRemoval: true)]
    private Collection $siteaccessAssignments;

    public function __construct()
    {
        $this->models = new ArrayCollection();
        $this->siteaccessAssignments = new ArrayCollection();
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

    public function getActiveChatModel(): ?AiModel
    {
        return $this->activeChatModel;
    }

    public function setActiveChatModel(?AiModel $model): self
    {
        $this->activeChatModel = $model;
        return $this;
    }

    public function getActiveImageModel(): ?AiModel
    {
        return $this->activeImageModel;
    }

    public function setActiveImageModel(?AiModel $model): self
    {
        $this->activeImageModel = $model;
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

    /**
     * @return Collection<int, AiProviderSiteaccess>
     */
    public function getSiteaccessAssignments(): Collection
    {
        return $this->siteaccessAssignments;
    }

    /**
     * @return list<string>
     */
    public function getSiteaccesses(): array
    {
        return $this->siteaccessAssignments
            ->map(fn (AiProviderSiteaccess $sa) => $sa->getSiteaccess())
            ->toArray();
    }

    public function addSiteaccess(string $siteaccess): self
    {
        if ($this->hasSiteaccess($siteaccess)) {
            return $this;
        }

        $assignment = new AiProviderSiteaccess();
        $assignment->setProvider($this);
        $assignment->setSiteaccess($siteaccess);
        $this->siteaccessAssignments->add($assignment);

        return $this;
    }

    public function removeSiteaccess(string $siteaccess): self
    {
        $toRemove = $this->siteaccessAssignments
            ->filter(fn (AiProviderSiteaccess $sa) => $sa->getSiteaccess() === $siteaccess);

        foreach ($toRemove as $assignment) {
            $this->siteaccessAssignments->removeElement($assignment);
        }

        return $this;
    }

    public function hasSiteaccess(string $siteaccess): bool
    {
        return $this->siteaccessAssignments
            ->exists(fn (int $i, AiProviderSiteaccess $sa) => $sa->getSiteaccess() === $siteaccess);
    }

    /**
     * Replace all siteaccess assignments.
     *
     * @param list<string> $siteaccesses
     */
    public function setSiteaccesses(array $siteaccesses): self
    {
        // Remove assignments no longer in the list
        $toRemove = $this->siteaccessAssignments->filter(
            fn (AiProviderSiteaccess $sa) => !in_array($sa->getSiteaccess(), $siteaccesses, true)
        );
        foreach ($toRemove as $assignment) {
            $this->siteaccessAssignments->removeElement($assignment);
        }

        // Add new assignments (addSiteaccess skips duplicates)
        foreach ($siteaccesses as $sa) {
            $this->addSiteaccess($sa);
        }

        return $this;
    }
}
