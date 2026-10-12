<?php

namespace App\Controller;

use App\Entity\Passage;
use App\Entity\Patient;
use App\Entity\Unite;
use App\Repository\PatientRepository;
use App\Repository\UniteRepository;
use App\Service\UniteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccueilController extends AbstractController
{
    #[Route('/accueil', name: 'app_accueil', methods: ['GET'])]
    public function index(
        PatientRepository $patientRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ACCUEIL');

        $uniteInitializer->ensureUnitsExist();

        // Récupérer les patients en cours triés par date d'arrivée récente
        $patients = $patientRepository->findBy(
            ['statut' => 'en cours'],
            ['dateArrivee' => 'DESC']
        );

        return $this->render('accueil/index.html.twig', [
            'patients' => $patients,
            'admissions_count' => count($patients),
        ]);
    }

    #[Route('/accueil/nouveau-patient', name: 'app_accueil_nouveau_patient', methods: ['POST'])]
    public function nouveauPatient(
        Request $request,
        EntityManagerInterface $em,
        UniteRepository $uniteRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ACCUEIL');

        $uniteInitializer->ensureUnitsExist();

        $nom = trim((string) $request->request->get('nom_prenom'));
        $age = $request->request->get('age') !== null ? (int) $request->request->get('age') : null;
        $sexe = trim((string) $request->request->get('sexe'));

        if (empty($nom)) {
            $this->addFlash('error', 'Le nom du patient est obligatoire.');
            return $this->redirectToRoute('app_accueil');
        }

        // Créer le Patient
        $patient = new Patient();
        $patient->setNom($nom);
        $patient->setAge($age);
        $patient->setSexe($sexe);
        $patient->setGravite(null); // Pas encore trié
        $patient->setDateArrivee(new \DateTime());
        $patient->setStatut('en cours');

        $em->persist($patient);

        // Retrouver l'Unité "Accueil"
        $uniteAccueil = $uniteRepository->findOneBy(['nom' => 'Accueil']);
        if ($uniteAccueil) {
            $passage = new Passage();
            $passage->setPatient($patient);
            $passage->setUnite($uniteAccueil);
            $passage->setHeureEntree(new \DateTime());
            $passage->setHeureSortie(null);
            $em->persist($passage);
        }

        $em->flush();

        if ($uniteAccueil) {
            $uniteInitializer->updateUnitCharge($uniteAccueil);
        }

        $this->addFlash('success', sprintf('Patient "%s" (Ticket #SU-%d) enregistré avec succès.', $patient->getNom(), $patient->getIdPatient()));

        return $this->redirectToRoute('app_accueil');
    }
}
