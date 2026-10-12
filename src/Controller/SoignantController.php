<?php

namespace App\Controller;

use App\Entity\Passage;
use App\Entity\Reaffectation;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Repository\AlerteRepository;
use App\Repository\PassageRepository;
use App\Repository\PatientRepository;
use App\Repository\PersonnelRepository;
use App\Repository\ReaffectationRepository;
use App\Repository\UniteRepository;
use App\Service\TriageService;
use App\Service\UniteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SoignantController extends AbstractController
{
    #[Route('/soignant', name: 'app_soignant', methods: ['GET'])]
    public function index(
        Request $request,
        PassageRepository $passageRepository,
        ReaffectationRepository $reaffectationRepository,
        AlerteRepository $alerteRepository,
        UniteRepository $uniteRepository,
        PatientRepository $patientRepository,
        PersonnelRepository $personnelRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        $uniteInitializer->ensureUnitsExist();

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        $personnel = $user?->getPersonnel();

        // Fallback: si l'utilisateur n'a pas la relation directe $user->getPersonnel(), chercher la fiche Personnel correspondante
        if (!$personnel && $user) {
            $personnel = $personnelRepository->findOneBy(['nom' => trim(($user->getNom() ?? '') . ' ' . ($user->getPrenom() ?? ''))])
                ?? $personnelRepository->findOneBy(['nom' => $user->getNom()]);
        }

        // 1. Unité fixe normale du personnel
        $uniteFixe = $user?->getUniteAssignee() ?? $personnel?->getUniteAssignee();
        if (!$uniteFixe) {
            // Par défaut, affecter l'unité "Box" pour la démo
            $uniteFixe = $uniteRepository->findOneBy(['nom' => 'Box']) ?? $uniteRepository->findAll()[0] ?? null;
        }

        // 2. Détecter si le personnel a une réaffectation active (réponse = 'acceptee' ET renfortTermine = false)
        $reaffectationActive = null;
        $isRenfort = false;
        $uniteActive = $uniteFixe;

        if ($personnel) {
            $reaffectationActive = $reaffectationRepository->findOneBy([
                'personnel' => $personnel,
                'reponse' => 'acceptee',
                'renfortTermine' => false,
            ], ['idReaffectation' => 'DESC']);

            if ($reaffectationActive) {
                // Déterminer l'unité cible (id_unite_cible ou alerte)
                $target = $reaffectationActive->getUniteCible() ?? $reaffectationActive->getAlerte()?->getUnite();
                if ($target) {
                    $uniteActive = $target;
                    $isRenfort = true;
                }
            }
        }

        // 3. Détecter si l'unité cible du renfort est spécifiquement "Triage"
        $modeTriageRenfort = false;
        $patientsAttenteTriage = [];
        $patientSelectionneTriage = null;
        $patientsTriesRecents = [];

        if ($isRenfort && $uniteActive && strtolower(trim($uniteActive->getNom())) === 'triage') {
            $modeTriageRenfort = true;
            $patientsAttenteTriage = $patientRepository->findPatientsEnAttenteTriage();

            $selectedPatientId = $request->query->get('patient_id');
            if ($selectedPatientId) {
                $patientSelectionneTriage = $patientRepository->find((int) $selectedPatientId);
            }
            if (!$patientSelectionneTriage && count($patientsAttenteTriage) > 0) {
                $patientSelectionneTriage = $patientsAttenteTriage[0];
            }

            $patientsTriesRecents = $patientRepository->findPatientsTriesRecents(20);
        }

        // Passages actifs dans cette unité
        $passagesActifs = [];
        if ($uniteActive) {
            $passagesActifs = $passageRepository->findBy(
                ['unite' => $uniteActive, 'heureSortie' => null],
                ['heureEntree' => 'ASC']
            );
        }

        // Notifications de réaffectation pour ce personnel (ou toutes en attente si non spécifié)
        if ($personnel) {
            $reaffectations = $reaffectationRepository->findBy([
                'personnel' => $personnel,
                'reponse' => 'en_attente',
            ]);
        } else {
            $reaffectations = $reaffectationRepository->findBy([
                'reponse' => 'en_attente',
            ]);
        }

        // Alertes actives pour le bandeau d'information
        $alertesActives = $alerteRepository->findBy(
            ['statut' => 'en_cours'],
            ['heure' => 'DESC']
        );

        return $this->render('soignant/index.html.twig', [
            'unite' => $uniteActive,
            'unite_fixe' => $uniteFixe,
            'is_renfort' => $isRenfort,
            'reaffectation_active' => $reaffectationActive,
            'passages' => $passagesActifs,
            'reaffectations' => $reaffectations,
            'alertes' => $alertesActives,
            'lits_occupes' => count($passagesActifs),
            'mode_triage_renfort' => $modeTriageRenfort,
            'patients_attente' => $patientsAttenteTriage,
            'patient_selectionne' => $patientSelectionneTriage,
            'patientSelectionne' => $patientSelectionneTriage,
            'patients_tries' => $patientsTriesRecents,
            'attente_count' => count($patientsAttenteTriage),
        ]);
    }

    #[Route('/soignant/sortie/{idPassage}', name: 'app_soignant_sortie', methods: ['POST'])]
    public function sortie(
        int $idPassage,
        PassageRepository $passageRepository,
        EntityManagerInterface $em,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        $passage = $passageRepository->find($idPassage);
        if (!$passage) {
            $this->addFlash('error', 'Passage introuvable.');
            return $this->redirectToRoute('app_soignant');
        }

        // Clôturer le passage
        $passage->setHeureSortie(new \DateTime());

        // Marquer le patient comme "sorti"
        $patient = $passage->getPatient();
        if ($patient) {
            $patient->setStatut('sorti');
        }

        $em->flush();

        // Mettre à jour la charge de l'unité
        $unite = $passage->getUnite();
        if ($unite) {
            $uniteInitializer->updateUnitCharge($unite);
        }

        $this->addFlash('success', sprintf('Sortie validée pour le patient %s.', $patient?->getNom() ?? '#'.$idPassage));

        return $this->redirectToRoute('app_soignant');
    }

    #[Route('/soignant/scan-sortie', name: 'app_soignant_scan_sortie', methods: ['POST'])]
    public function scanSortie(
        Request $request,
        PatientRepository $patientRepository,
        PassageRepository $passageRepository,
        EntityManagerInterface $em,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        $patientId = $request->request->get('patient_id');
        if (!$patientId) {
            $this->addFlash('error', 'Aucun identifiant de patient fourni.');
            return $this->redirectToRoute('app_soignant');
        }

        $patient = $patientRepository->find((int) $patientId);
        if (!$patient) {
            $this->addFlash('error', sprintf('Patient #%s introuvable.', $patientId));
            return $this->redirectToRoute('app_soignant');
        }

        // Trouver le passage actif de ce patient
        $passage = $passageRepository->findOneBy([
            'patient' => $patient,
            'heureSortie' => null,
        ]);

        if (!$passage) {
            $this->addFlash('error', sprintf('Aucun passage actif trouvé pour le patient %s (#SU-%d).', $patient->getNom(), $patient->getIdPatient()));
            return $this->redirectToRoute('app_soignant');
        }

        // Clôturer le passage
        $passage->setHeureSortie(new \DateTime());
        $patient->setStatut('sorti');
        $em->flush();

        // Mettre à jour la charge de l'unité
        $unite = $passage->getUnite();
        if ($unite) {
            $uniteInitializer->updateUnitCharge($unite);
        }

        $this->addFlash('success', sprintf('Sortie enregistrée avec succès pour le patient %s (#SU-%d).', $patient->getNom(), $patient->getIdPatient()));

        return $this->redirectToRoute('app_soignant');
    }

    #[Route('/soignant/notification/{id}/reponse', name: 'app_soignant_notification_reponse', methods: ['POST'])]
    public function repondreNotification(
        int $id,
        Request $request,
        ReaffectationRepository $reaffectationRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        $reaffectation = $reaffectationRepository->find($id);
        if (!$reaffectation) {
            $this->addFlash('error', 'Notification de réaffectation introuvable.');
            return $this->redirectToRoute('app_soignant');
        }

        $reponse = trim((string) $request->request->get('reponse')); // 'acceptee' ou 'refusee'
        if (!in_array($reponse, ['acceptee', 'refusee'], true)) {
            $this->addFlash('error', 'Réponse invalide.');
            return $this->redirectToRoute('app_soignant');
        }

        $reaffectation->setReponse($reponse);

        // Si acceptée et si uniteCible est nulle, la renseigner depuis l'unité de l'alerte
        if ($reponse === 'acceptee') {
            if (!$reaffectation->getUniteCible() && $reaffectation->getAlerte()?->getUnite()) {
                $reaffectation->setUniteCible($reaffectation->getAlerte()->getUnite());
            }
        }

        $em->flush();

        $this->addFlash('success', sprintf('Demande de renfort %s.', $reponse === 'acceptee' ? 'acceptée' : 'refusée'));

        return $this->redirectToRoute('app_soignant');
    }

    #[Route('/soignant/terminer-renfort/{idReaffectation}', name: 'app_soignant_terminer_renfort', methods: ['POST'])]
    public function terminerRenfort(
        int $idReaffectation,
        ReaffectationRepository $reaffectationRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        $reaffectation = $reaffectationRepository->find($idReaffectation);
        if (!$reaffectation) {
            $this->addFlash('error', 'Réaffectation introuvable.');
            return $this->redirectToRoute('app_soignant');
        }

        $reaffectation->setRenfortTermine(true);
        $em->flush();

        $uniteFixe = $reaffectation->getPersonnel()?->getUniteAssignee()?->getNom() ?? 'votre unité fixe';
        $this->addFlash('success', sprintf('Renfort terminé avec succès. Vous avez réintégré %s.', $uniteFixe));

        return $this->redirectToRoute('app_soignant');
    }

    #[Route('/soignant/evaluer-en-renfort/{idPatient}', name: 'app_soignant_evaluer_en_renfort', methods: ['POST'])]
    public function evaluerEnRenfort(
        int $idPatient,
        Request $request,
        PatientRepository $patientRepository,
        PersonnelRepository $personnelRepository,
        ReaffectationRepository $reaffectationRepository,
        TriageService $triageService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SOIGNANT');

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        $personnel = $user?->getPersonnel();

        if (!$personnel && $user) {
            $personnel = $personnelRepository->findOneBy(['nom' => trim(($user->getNom() ?? '') . ' ' . ($user->getPrenom() ?? ''))])
                ?? $personnelRepository->findOneBy(['nom' => $user->getNom()]);
        }

        if (!$personnel) {
            throw new AccessDeniedException('Aucun profil personnel associé à ce compte.');
        }

        // Sécurité : vérifier que le soignant a une réaffectation active vers l'unité Triage
        $reaffectationActive = $reaffectationRepository->findOneBy([
            'personnel' => $personnel,
            'reponse' => 'acceptee',
            'renfortTermine' => false,
        ], ['idReaffectation' => 'DESC']);

        $uniteTarget = $reaffectationActive?->getUniteCible() ?? $reaffectationActive?->getAlerte()?->getUnite();

        if (!$reaffectationActive || !$uniteTarget || strtolower(trim($uniteTarget->getNom())) !== 'triage') {
            $this->addFlash('error', 'Accès refusé : vous devez avoir une réaffectation active vers le Triage pour effectuer cette action.');
            return $this->redirectToRoute('app_soignant');
        }

        $patient = $patientRepository->find($idPatient);
        if (!$patient) {
            $this->addFlash('error', 'Patient introuvable.');
            return $this->redirectToRoute('app_soignant');
        }

        $gravite = (string) $request->request->get('gravite');
        $nomUniteDestination = (string) $request->request->get('unite_destination', 'Box');

        $result = $triageService->evaluerPatient($patient, $gravite, $nomUniteDestination);

        if ($result['success']) {
            $this->addFlash('success', $result['message']);
            if ($result['warning']) {
                $this->addFlash('warning', $result['warning']);
            }
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('app_soignant');
    }
}
