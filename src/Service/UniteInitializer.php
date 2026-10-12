<?php

namespace App\Service;

use App\Entity\Unite;
use App\Repository\UniteRepository;
use Doctrine\ORM\EntityManagerInterface;

class UniteInitializer
{
    public function __construct(
        private EntityManagerInterface $em,
        private UniteRepository $uniteRepository
    ) {}

    /**
     * S'assure que les 5 unités fondamentales du service des urgences existent en BDD.
     */
    public function ensureUnitsExist(): void
    {
        $unitsConfig = [
            'Accueil' => 20,
            'Triage'  => 8,
            'Box'     => 20,
            'SAUV'    => 10,
            'UHCD'    => 15,
        ];

        $changed = false;

        foreach ($unitsConfig as $nom => $capaciteMax) {
            $unite = $this->uniteRepository->findOneBy(['nom' => $nom]);
            if (!$unite) {
                $unite = new Unite();
                $unite->setNom($nom);
                $unite->setCapaciteMax($capaciteMax);
                $unite->setChargeActuelle(0);
                $this->em->persist($unite);
                $changed = true;
            }
        }

        if ($changed) {
            $this->em->flush();
        }
    }

    /**
     * Recalcule la charge actuelle d'une unité à partir des passages actifs (heure_sortie IS NULL).
     */
    public function updateUnitCharge(Unite $unite): void
    {
        $activePassagesCount = 0;
        foreach ($unite->getPassages() as $passage) {
            if ($passage->getHeureSortie() === null) {
                $activePassagesCount++;
            }
        }

        $unite->setChargeActuelle($activePassagesCount);
        $this->em->flush();
    }
}
