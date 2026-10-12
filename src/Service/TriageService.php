<?php

namespace App\Service;

use App\Entity\Alerte;
use App\Entity\Passage;
use App\Entity\Patient;
use App\Entity\Unite;
use App\Repository\PassageRepository;
use App\Repository\UniteRepository;
use Doctrine\ORM\EntityManagerInterface;

class TriageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PassageRepository $passageRepository,
        private UniteRepository $uniteRepository,
        private UniteInitializer $uniteInitializer
    ) {}

    /**
     * Effectue l'évaluation d'un patient au triage (gravité + orientation unité destination).
     *
     * @return array{success: bool, message: string, warning: string|null}
     */
    public function evaluerPatient(Patient $patient, string $gravite, string $nomUniteDestination): array
    {
        $this->uniteInitializer->ensureUnitsExist();

        $gravite = strtolower(trim($gravite));
        if (!in_array($gravite, ['rouge', 'orange', 'jaune', 'vert'], true)) {
            return [
                'success' => false,
                'message' => 'Niveau de gravité invalide (Rouge, Orange, Jaune, Vert).',
                'warning' => null,
            ];
        }

        // 1. Mettre à jour la gravité du patient
        $patient->setGravite($gravite);

        // 2. Clôturer le passage actuel du patient (s'il en a un actif)
        $passageActif = $this->passageRepository->findOneBy([
            'patient' => $patient,
            'heureSortie' => null,
        ]);

        if ($passageActif) {
            $passageActif->setHeureSortie(new \DateTime());
        }

        // 3. Trouver ou assigner l'unité de destination
        $uniteDestination = $this->uniteRepository->findOneBy(['nom' => $nomUniteDestination]);
        if (!$uniteDestination) {
            // Unité fallback selon gravité
            if ($gravite === 'rouge') {
                $uniteDestination = $this->uniteRepository->findOneBy(['nom' => 'SAUV']);
            } else {
                $uniteDestination = $this->uniteRepository->findOneBy(['nom' => 'Box']);
            }
        }

        if ($uniteDestination) {
            // Créer le nouveau Passage dans l'unité de destination
            $nouveauPassage = new Passage();
            $nouveauPassage->setPatient($patient);
            $nouveauPassage->setUnite($uniteDestination);
            $nouveauPassage->setHeureEntree(new \DateTime());
            $nouveauPassage->setHeureSortie(null);
            $this->em->persist($nouveauPassage);
        }

        $this->em->flush();

        $warning = null;

        // 4. Mettre à jour la charge actuelle de l'unité de destination & vérifier saturation
        if ($uniteDestination) {
            $this->uniteInitializer->updateUnitCharge($uniteDestination);

            if ($uniteDestination->getChargeActuelle() > $uniteDestination->getCapaciteMax()) {
                // Créer automatiquement une nouvelle alerte de saturation
                $alerte = new Alerte();
                $alerte->setUnite($uniteDestination);
                $alerte->setType('saturation');
                $alerte->setStatut('en_cours');
                $alerte->setHeure(new \DateTime());
                $this->em->persist($alerte);
                $this->em->flush();

                $warning = sprintf(
                    'Attention: L\'unité %s a dépassé sa capacité maximale (%d/%d)! Alerte créée.',
                    $uniteDestination->getNom(),
                    $uniteDestination->getChargeActuelle(),
                    $uniteDestination->getCapaciteMax()
                );
            }
        }

        $message = sprintf(
            'Patient %s trié (Code %s) et réorienté vers %s.',
            $patient->getNom(),
            strtoupper($gravite),
            $uniteDestination?->getNom() ?? 'Unité'
        );

        return [
            'success' => true,
            'message' => $message,
            'warning' => $warning,
        ];
    }
}
