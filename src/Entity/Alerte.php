<?php

namespace App\Entity;

use App\Repository\AlerteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlerteRepository::class)]
#[ORM\Table(name: 'alerte')]
class Alerte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_alerte', type: 'integer')]
    private ?int $idAlerte = null;

    #[ORM\ManyToOne(targetEntity: Unite::class, inversedBy: 'alertes')]
    #[ORM\JoinColumn(name: 'id_unite', referencedColumnName: 'id_unite', nullable: false, onDelete: 'CASCADE')]
    private ?Unite $unite = null;

    #[ORM\Column(name: 'type', length: 100, nullable: true)]
    private ?string $type = null; // ex: 'saturation', 'attente_longue'

    #[ORM\Column(name: 'heure', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column(name: 'statut', length: 50, nullable: true)]
    private ?string $statut = null; // 'en_cours', 'traitee'

    #[ORM\OneToMany(mappedBy: 'alerte', targetEntity: Reaffectation::class)]
    private Collection $reaffectations;

    public function __construct()
    {
        $this->reaffectations = new ArrayCollection();
        $this->heure = new \DateTime();
        $this->statut = 'en_cours';
    }

    public function getIdAlerte(): ?int
    {
        return $this->idAlerte;
    }

    public function getUnite(): ?Unite
    {
        return $this->unite;
    }

    public function setUnite(?Unite $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getHeure(): ?\DateTime
    {
        return $this->heure;
    }

    public function setHeure(?\DateTime $heure): static
    {
        $this->heure = $heure;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection<int, Reaffectation>
     */
    public function getReaffectations(): Collection
    {
        return $this->reaffectations;
    }

    public function addReaffectation(Reaffectation $reaffectation): static
    {
        if (!$this->reaffectations->contains($reaffectation)) {
            $this->reaffectations->add($reaffectation);
            $reaffectation->setAlerte($this);
        }

        return $this;
    }

    public function removeReaffectation(Reaffectation $reaffectation): static
    {
        if ($this->reaffectations->removeElement($reaffectation)) {
            // set the owning side to null (unless already changed)
            if ($reaffectation->getAlerte() === $this) {
                $reaffectation->setAlerte(null);
            }
        }

        return $this;
    }
}
