<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\Table(name: 'patient')]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_patient', type: 'integer')]
    private ?int $idPatient = null;

    #[ORM\Column(name: 'nom', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: 'age', nullable: true)]
    private ?int $age = null;

    #[ORM\Column(name: 'sexe', length: 10, nullable: true, enumType: null)]
    private ?string $sexe = null; // 'Homme' ou 'Femme'

    #[ORM\Column(name: 'gravite', length: 50, nullable: true)]
    private ?string $gravite = null;

    #[ORM\Column(name: 'date_arrivee', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateArrivee = null;

    #[ORM\Column(name: 'statut', length: 50, nullable: true)]
    private ?string $statut = null; // ex: 'en cours', 'sorti'

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Passage::class, orphanRemoval: true)]
    private Collection $passages;

    public function __construct()
    {
        $this->passages = new ArrayCollection();
        $this->dateArrivee = new \DateTime();
        $this->statut = 'en cours';
    }

    public function getIdPatient(): ?int
    {
        return $this->idPatient;
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

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getGravite(): ?string
    {
        return $this->gravite;
    }

    public function setGravite(?string $gravite): static
    {
        $this->gravite = $gravite;

        return $this;
    }

    public function getDateArrivee(): ?\DateTime
    {
        return $this->dateArrivee;
    }

    public function setDateArrivee(?\DateTime $dateArrivee): static
    {
        $this->dateArrivee = $dateArrivee;

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
     * @return Collection<int, Passage>
     */
    public function getPassages(): Collection
    {
        return $this->passages;
    }

    public function addPassage(Passage $passage): static
    {
        if (!$this->passages->contains($passage)) {
            $this->passages->add($passage);
            $passage->setPatient($this);
        }

        return $this;
    }

    public function removePassage(Passage $passage): static
    {
        if ($this->passages->removeElement($passage)) {
            // set the owning side to null (unless already changed)
            if ($passage->getPatient() === $this) {
                $passage->setPatient(null);
            }
        }

        return $this;
    }
}
