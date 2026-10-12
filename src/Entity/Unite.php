<?php

namespace App\Entity;

use App\Repository\UniteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UniteRepository::class)]
#[ORM\Table(name: 'unite')]
class Unite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_unite', type: 'integer')]
    private ?int $idUnite = null;

    #[ORM\Column(name: 'nom', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: 'capacite_max')]
    private ?int $capaciteMax = null;

    #[ORM\Column(name: 'charge_actuelle', nullable: true, options: ['default' => 0])]
    private ?int $chargeActuelle = 0;

    #[ORM\OneToMany(mappedBy: 'unite', targetEntity: Passage::class)]
    private Collection $passages;

    #[ORM\OneToMany(mappedBy: 'unite', targetEntity: Alerte::class)]
    private Collection $alertes;

    #[ORM\OneToMany(mappedBy: 'uniteAssignee', targetEntity: Personnel::class)]
    private Collection $personnels;

    public function __construct()
    {
        $this->passages = new ArrayCollection();
        $this->alertes = new ArrayCollection();
        $this->personnels = new ArrayCollection();
    }

    public function getIdUnite(): ?int
    {
        return $this->idUnite;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;

        return $this;
    }

    public function getChargeActuelle(): ?int
    {
        return $this->chargeActuelle;
    }

    public function setChargeActuelle(?int $chargeActuelle): static
    {
        $this->chargeActuelle = $chargeActuelle;

        return $this;
    }

    /**
     * Vrai si la charge actuelle dépasse la capacité max — utilisé par l'Agent Unité.
     */
    public function estSature(): bool
    {
        return $this->chargeActuelle !== null
            && $this->capaciteMax !== null
            && $this->chargeActuelle >= $this->capaciteMax;
    }

    /**
     * @return Collection<int, Passage>
     */
    public function getPassages(): Collection
    {
        return $this->passages;
    }

    /**
     * @return Collection<int, Alerte>
     */
    public function getAlertes(): Collection
    {
        return $this->alertes;
    }

    /**
     * @return Collection<int, Personnel>
     */
    public function getPersonnels(): Collection
    {
        return $this->personnels;
    }

    public function addPassage(Passage $passage): static
    {
        if (!$this->passages->contains($passage)) {
            $this->passages->add($passage);
            $passage->setUnite($this);
        }

        return $this;
    }

    public function removePassage(Passage $passage): static
    {
        if ($this->passages->removeElement($passage)) {
            // set the owning side to null (unless already changed)
            if ($passage->getUnite() === $this) {
                $passage->setUnite(null);
            }
        }

        return $this;
    }

    public function addAlerte(Alerte $alerte): static
    {
        if (!$this->alertes->contains($alerte)) {
            $this->alertes->add($alerte);
            $alerte->setUnite($this);
        }

        return $this;
    }

    public function removeAlerte(Alerte $alerte): static
    {
        if ($this->alertes->removeElement($alerte)) {
            // set the owning side to null (unless already changed)
            if ($alerte->getUnite() === $this) {
                $alerte->setUnite(null);
            }
        }

        return $this;
    }

    public function addPersonnel(Personnel $personnel): static
    {
        if (!$this->personnels->contains($personnel)) {
            $this->personnels->add($personnel);
            $personnel->setUniteAssignee($this);
        }

        return $this;
    }

    public function removePersonnel(Personnel $personnel): static
    {
        if ($this->personnels->removeElement($personnel)) {
            // set the owning side to null (unless already changed)
            if ($personnel->getUniteAssignee() === $this) {
                $personnel->setUniteAssignee(null);
            }
        }

        return $this;
    }
}
