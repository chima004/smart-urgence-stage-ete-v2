<?php

namespace App\Entity;

use App\Repository\PredictionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PredictionRepository::class)]
#[ORM\Table(name: 'prediction')]
class Prediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_prediction', type: 'integer')]
    private ?int $idPrediction = null;

    #[ORM\Column(name: 'date_cible', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCible = null;

    #[ORM\Column(name: 'flux_estime', nullable: true)]
    private ?int $fluxEstime = null;

    #[ORM\Column(name: 'modele_utilise', length: 100, nullable: true)]
    private ?string $modeleUtilise = null;

    public function getIdPrediction(): ?int
    {
        return $this->idPrediction;
    }

    public function getDateCible(): ?\DateTime
    {
        return $this->dateCible;
    }

    public function setDateCible(?\DateTime $dateCible): static
    {
        $this->dateCible = $dateCible;

        return $this;
    }

    public function getFluxEstime(): ?int
    {
        return $this->fluxEstime;
    }

    public function setFluxEstime(?int $fluxEstime): static
    {
        $this->fluxEstime = $fluxEstime;

        return $this;
    }

    public function getModeleUtilise(): ?string
    {
        return $this->modeleUtilise;
    }

    public function setModeleUtilise(?string $modeleUtilise): static
    {
        $this->modeleUtilise = $modeleUtilise;

        return $this;
    }
}
