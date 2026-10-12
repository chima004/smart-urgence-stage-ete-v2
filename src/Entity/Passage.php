<?php

namespace App\Entity;

use App\Repository\PassageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PassageRepository::class)]
#[ORM\Table(name: 'passage')]
class Passage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_passage', type: 'integer')]
    private ?int $idPassage = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'passages')]
    #[ORM\JoinColumn(name: 'id_patient', referencedColumnName: 'id_patient', nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Unite::class, inversedBy: 'passages')]
    #[ORM\JoinColumn(name: 'id_unite', referencedColumnName: 'id_unite', nullable: false, onDelete: 'CASCADE')]
    private ?Unite $unite = null;

    #[ORM\Column(name: 'heure_entree', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureEntree = null;

    #[ORM\Column(name: 'heure_sortie', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureSortie = null;

    public function getIdPassage(): ?int
    {
        return $this->idPassage;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
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

    public function getHeureEntree(): ?\DateTime
    {
        return $this->heureEntree;
    }

    public function setHeureEntree(?\DateTime $heureEntree): static
    {
        $this->heureEntree = $heureEntree;

        return $this;
    }

    public function getHeureSortie(): ?\DateTime
    {
        return $this->heureSortie;
    }

    public function setHeureSortie(?\DateTime $heureSortie): static
    {
        $this->heureSortie = $heureSortie;

        return $this;
    }

    /**
     * Vrai si le patient est toujours présent dans cette unité (pas encore scanné en sortie).
     * C'est ce champ calculé que l'Agent Unité utilise pour recompter charge_actuelle.
     */
    public function estEnCours(): bool
    {
        return $this->heureSortie === null;
    }
}
