<?php

namespace App\Entity;

use App\Repository\ReaffectationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReaffectationRepository::class)]
#[ORM\Table(name: 'reaffectation')]
class Reaffectation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reaffectation', type: 'integer')]
    private ?int $idReaffectation = null;

    #[ORM\ManyToOne(targetEntity: Alerte::class, inversedBy: 'reaffectations')]
    #[ORM\JoinColumn(name: 'id_alerte', referencedColumnName: 'id_alerte', nullable: false, onDelete: 'CASCADE')]
    private ?Alerte $alerte = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class, inversedBy: 'reaffectations')]
    #[ORM\JoinColumn(name: 'id_personnel', referencedColumnName: 'id_personnel', nullable: false, onDelete: 'CASCADE')]
    private ?Personnel $personnel = null;

    #[ORM\Column(name: 'heure_notification', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureNotification = null;

    #[ORM\Column(name: 'reponse', length: 100, nullable: true)]
    private ?string $reponse = null; // 'acceptee', 'refusee', 'en_attente'

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(name: 'id_unite_cible', referencedColumnName: 'id_unite', nullable: true, onDelete: 'SET NULL')]
    private ?Unite $uniteCible = null;

    #[ORM\Column(name: 'renfort_termine', type: 'boolean', options: ['default' => false])]
    private bool $renfortTermine = false;

    public function __construct()
    {
        $this->heureNotification = new \DateTime();
        $this->reponse = 'en_attente';
        $this->renfortTermine = false;
    }

    public function getIdReaffectation(): ?int
    {
        return $this->idReaffectation;
    }

    public function getAlerte(): ?Alerte
    {
        return $this->alerte;
    }

    public function setAlerte(?Alerte $alerte): static
    {
        $this->alerte = $alerte;

        return $this;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;

        return $this;
    }

    public function getHeureNotification(): ?\DateTime
    {
        return $this->heureNotification;
    }

    public function setHeureNotification(?\DateTime $heureNotification): static
    {
        $this->heureNotification = $heureNotification;

        return $this;
    }

    public function getReponse(): ?string
    {
        return $this->reponse;
    }

    public function setReponse(?string $reponse): static
    {
        $this->reponse = $reponse;

        return $this;
    }

    public function getUniteCible(): ?Unite
    {
        return $this->uniteCible;
    }

    public function setUniteCible(?Unite $uniteCible): static
    {
        $this->uniteCible = $uniteCible;

        return $this;
    }

    public function isRenfortTermine(): bool
    {
        return $this->renfortTermine;
    }

    public function setRenfortTermine(bool $renfortTermine): static
    {
        $this->renfortTermine = $renfortTermine;

        return $this;
    }
}
