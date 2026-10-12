<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Passage;
use App\Entity\Patient;
use App\Entity\Unite;
use App\Repository\PassageRepository;
use App\Repository\PatientRepository;
use App\Repository\UniteRepository;
use App\Service\TriageService;
use App\Service\UniteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TriageController extends AbstractController
{
    #[Route('/triage', name: 'app_triage', methods: ['GET'])]
    public function index(
        Request $request,
        PatientRepository $patientRepository,
        PassageRepository $passageRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_TRIAGE');

        $uniteInitializer->ensureUnitsExist();

        // 1. Patients en attente de triage
        $patientsEnAttente = $patientRepository->findPatientsEnAttenteTriage();

        // 2. Patient sélectionné (spécifique via scanner / recherche manuelle ou premier de la file)
        $selectedPatientId = $request->query->get('patient_id');
        $patientSelectionne = null;
        if ($selectedPatientId) {
            $patientSelectionne = $patientRepository->find((int) $selectedPatientId);
        }
        if (!$patientSelectionne && count($patientsEnAttente) > 0) {
            $patientSelectionne = $patientsEnAttente[0];
        }

        // 3. Patients déjà triés aujourd'hui
        $patientsTries = $patientRepository->findPatientsTriesRecents(20);

        return $this->render('triage/index.html.twig', [
            'patients_attente' => $patientsEnAttente,
            'patients_tries' => $patientsTries,
            'patient_selectionne' => $patientSelectionne,
            'patientSelectionne' => $patientSelectionne,
            'attente_count' => count($patientsEnAttente),
        ]);
    }

    #[Route('/triage/evaluer/{id}', name: 'app_triage_evaluer', methods: ['POST'])]
    public function evaluer(
        int $id,
        Request $request,
        PatientRepository $patientRepository,
        TriageService $triageService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_TRIAGE');

        $patient = $patientRepository->find($id);
        if (!$patient) {
            $this->addFlash('error', 'Patient introuvable.');
            return $this->redirectToRoute('app_triage');
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

        return $this->redirectToRoute('app_triage');
    }
}
