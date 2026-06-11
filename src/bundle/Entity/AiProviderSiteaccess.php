<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_ai_provider_siteaccess')]
#[ORM\UniqueConstraint(name: 'uniq_provider_sa', columns: ['provider_id', 'siteaccess'])]
class AiProviderSiteaccess
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AiProvider::class, inversedBy: 'siteaccessAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AiProvider $provider = null;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $siteaccess = '';

    public function getProvider(): ?AiProvider
    {
        return $this->provider;
    }

    public function setProvider(AiProvider $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getSiteaccess(): string
    {
        return $this->siteaccess;
    }

    public function setSiteaccess(string $siteaccess): self
    {
        $this->siteaccess = $siteaccess;
        return $this;
    }
}
