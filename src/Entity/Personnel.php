<?php

namespace App\Entity;

use App\Repository\PersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[ORM\Table(name: 'personnel')]
class Personnel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_personnel', type: 'integer')]
    private ?int $idPersonnel = null;

    #[ORM\Column(name: 'nom', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: 'role', length: 50, nullable: true)]
    private ?string $role = null; // ex: 'infirmier', 'medecin'

    #[ORM\Column(name: 'disponibilite', nullable: true, options: ['default' => 1])]
    private ?bool $disponibilite = true;

    #[ORM\ManyToOne(targetEntity: Unite::class, inversedBy: 'personnels')]
    #[ORM\JoinColumn(name: 'id_unite_assignee', referencedColumnName: 'id_unite', nullable: true, onDelete: 'SET NULL')]
    private ?Unite $uniteAssignee = null;

    #[ORM\OneToMany(mappedBy: 'personnel', targetEntity: Reaffectation::class)]
    private Collection $reaffectations;

    public function __construct()
    {
        $this->reaffectations = new ArrayCollection();
    }

    public function getIdPersonnel(): ?int
    {
        return $this->idPersonnel;
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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isDisponibilite(): ?bool
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?bool $disponibilite): static
    {
        $this->disponibilite = $disponibilite;

        return $this;
    }

    public function getUniteAssignee(): ?Unite
    {
        return $this->uniteAssignee;
    }

    public function setUniteAssignee(?Unite $uniteAssignee): static
    {
        $this->uniteAssignee = $uniteAssignee;

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
            $reaffectation->setPersonnel($this);
        }

        return $this;
    }

    public function removeReaffectation(Reaffectation $reaffectation): static
    {
        if ($this->reaffectations->removeElement($reaffectation)) {
            // set the owning side to null (unless already changed)
            if ($reaffectation->getPersonnel() === $this) {
                $reaffectation->setPersonnel(null);
            }
        }

        return $this;
    }
}
