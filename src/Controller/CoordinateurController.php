<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Reaffectation;

use App\Repository\AlerteRepository;
use App\Repository\PatientRepository;
use App\Repository\PersonnelRepository;
use App\Repository\PredictionRepository;
use App\Repository\ReaffectationRepository;
use App\Repository\UniteRepository;
use App\Service\UniteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CoordinateurController extends AbstractController
{
    #[Route('/coordinateur', name: 'app_coordinateur', methods: ['GET'])]
    public function dashboard(
        UniteRepository $uniteRepository,
        AlerteRepository $alerteRepository,
        PatientRepository $patientRepository,
        PersonnelRepository $personnelRepository,
        PredictionRepository $predictionRepository,
        ReaffectationRepository $reaffectationRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COORDINATEUR');

        $uniteInitializer->ensureUnitsExist();

        // 1. Récupérer toutes les unités et recalculer leur charge
        $unités = $uniteRepository->findAll();
        foreach ($unités as $unite) {
            $uniteInitializer->updateUnitCharge($unite);
        }

        // 2. Alertes en cours
        $alertesActives = $alerteRepository->findBy(
            ['statut' => 'en_cours'],
            ['heure' => 'DESC']
        );

        // Toutes les alertes pour l'historique
        $toutesAlertes = $alerteRepository->findBy([], ['heure' => 'DESC']);

        // 3. Nombre de patients en cours
        $patientsEnCoursCount = count($patientRepository->findBy(['statut' => 'en cours']));

        // 4. Liste du personnel disponible pour la réaffectation
        $personnelDisponible = $personnelRepository->findBy(['disponibilite' => true]);

        // 5. Récupération des prédictions (Agent SPADE Prediction)
        $recentPredictions = $predictionRepository->findRecentPredictions(6);
        $latestPrediction = $recentPredictions[0] ?? null;

        // 6. Récupération de l'historique et statut des réaffectations
        $reaffectations = $reaffectationRepository->findBy([], ['idReaffectation' => 'DESC']);

        return $this->render('coordinateur/dashboard.html.twig', [
            'unites' => $unités,
            'alertes_actives' => $alertesActives,
            'toutes_alertes' => $toutesAlertes,
            'patients_count' => $patientsEnCoursCount,
            'alertes_count' => count($alertesActives),
            'personnels_disponibles' => $personnelDisponible,
            'predictions' => $recentPredictions,
            'latest_prediction' => $latestPrediction,
            'reaffectations' => $reaffectations,
        ]);
    }

    #[Route('/coordinateur/alerte/{id}/traiter', name: 'app_coordinateur_traiter_alerte', methods: ['POST'])]
    public function traiterAlerte(
        int $id,
        AlerteRepository $alerteRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COORDINATEUR');

        $alerte = $alerteRepository->find($id);
        if (!$alerte) {
            $this->addFlash('error', 'Alerte introuvable.');
            return $this->redirectToRoute('app_coordinateur');
        }

        $alerte->setStatut('traitee');
        $em->flush();

        $this->addFlash('success', sprintf('Alerte #%d (%s) marquée comme traitée.', $alerte->getIdAlerte(), $alerte->getUnite()?->getNom() ?? 'Unité'));

        return $this->redirectToRoute('app_coordinateur');
    }

    #[Route('/coordinateur/reaffecter', name: 'app_coordinateur_reaffecter', methods: ['POST'])]
    public function reaffecter(
        Request $request,
        AlerteRepository $alerteRepository,
        PersonnelRepository $personnelRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COORDINATEUR');

        $alerteId = (int) $request->request->get('alerte_id');
        $personnelId = (int) $request->request->get('personnel_id');

        $alerte = $alerteRepository->find($alerteId);
        $personnel = $personnelRepository->find($personnelId);

        if (!$alerte || !$personnel) {
            $this->addFlash('error', 'Sélection d\'alerte ou de personnel invalide.');
            return $this->redirectToRoute('app_coordinateur');
        }

        // Créer la demande de réaffectation
        $reaffectation = new Reaffectation();
        $reaffectation->setAlerte($alerte);
        $reaffectation->setPersonnel($personnel);
        $reaffectation->setUniteCible($alerte->getUnite());
        $reaffectation->setHeureNotification(new \DateTime());
        $reaffectation->setReponse('en_attente');

        $em->persist($reaffectation);
        $em->flush();

        $this->addFlash('success', sprintf('Demande de renfort envoyée à %s pour l\'unité %s.', $personnel->getNom(), $alerte->getUnite()?->getNom() ?? 'Unité'));

        return $this->redirectToRoute('app_coordinateur');
    }
}
