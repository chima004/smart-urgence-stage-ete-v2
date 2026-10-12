<?php

namespace App\Controller;

use App\Entity\Personnel;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Repository\AlerteRepository;
use App\Repository\PassageRepository;
use App\Repository\PatientRepository;
use App\Repository\PersonnelRepository;
use App\Repository\ReaffectationRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UniteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        UniteRepository $uniteRepository,
        PatientRepository $patientRepository,
        AlerteRepository $alerteRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $uniteInitializer->ensureUnitsExist();

        $utilisateurs = $utilisateurRepository->findBy([], ['dateCreation' => 'DESC']);
        $unites = $uniteRepository->findAll();

        // Métriques globales du système en lecture seule (sans possibilité d'action sur les patients)
        $patientsCount = count($patientRepository->findBy(['statut' => 'en cours']));
        $alertesCount = count($alerteRepository->findBy(['statut' => 'en_cours']));

        return $this->render('admin/dashboard.html.twig', [
            'utilisateurs' => $utilisateurs,
            'unites' => $unites,
            'patients_count' => $patientsCount,
            'alertes_count' => $alertesCount,
        ]);
    }

    /* =========================================================================
       SUPERVISION GLOBALE DES MODULES (LECTURE SEULE STRICTE)
       ========================================================================= */

    #[Route('/admin/supervision/accueil', name: 'app_admin_supervision_accueil', methods: ['GET'])]
    public function supervisionAccueil(
        PatientRepository $patientRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $uniteInitializer->ensureUnitsExist();

        $patients = $patientRepository->findBy(
            ['statut' => 'en cours'],
            ['dateArrivee' => 'DESC']
        );

        return $this->render('admin/supervision/accueil.html.twig', [
            'patients' => $patients,
            'admissions_count' => count($patients),
        ]);
    }

    #[Route('/admin/supervision/triage', name: 'app_admin_supervision_triage', methods: ['GET'])]
    public function supervisionTriage(
        PatientRepository $patientRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $uniteInitializer->ensureUnitsExist();

        $patientsEnAttente = $patientRepository->findPatientsEnAttenteTriage();
        $patientsTries = $patientRepository->findPatientsTriesRecents(20);

        return $this->render('admin/supervision/triage.html.twig', [
            'patients_attente' => $patientsEnAttente,
            'patients_tries' => $patientsTries,
            'attente_count' => count($patientsEnAttente),
        ]);
    }

    #[Route('/admin/supervision/soignant/{idUnite}', name: 'app_admin_supervision_soignant', methods: ['GET'], defaults: ['idUnite' => null])]
    public function supervisionSoignant(
        ?int $idUnite,
        UniteRepository $uniteRepository,
        PassageRepository $passageRepository,
        AlerteRepository $alerteRepository,
        ReaffectationRepository $reaffectationRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $uniteInitializer->ensureUnitsExist();

        $unites = $uniteRepository->findAll();

        $unite = null;
        if ($idUnite) {
            $unite = $uniteRepository->find($idUnite);
        }

        // Si idUnite n'est pas spécifié ou introuvable, afficher par défaut l'unité "Box" (ou la 1ère unité trouvée)
        if (!$unite) {
            $unite = $uniteRepository->findOneBy(['nom' => 'Box']) ?? ($unites[0] ?? null);
        }

        $passagesActifs = [];
        if ($unite) {
            $passagesActifs = $passageRepository->findBy(
                ['unite' => $unite, 'heureSortie' => null],
                ['heureEntree' => 'ASC']
            );
        }

        $reaffectations = $reaffectationRepository->findBy(['reponse' => 'en_attente']);
        $alertesActives = $alerteRepository->findBy(['statut' => 'en_cours'], ['heure' => 'DESC']);

        return $this->render('admin/supervision/soignant.html.twig', [
            'unites' => $unites,
            'unite' => $unite,
            'passages' => $passagesActifs,
            'reaffectations' => $reaffectations,
            'alertes' => $alertesActives,
            'lits_occupes' => count($passagesActifs),
        ]);
    }

    #[Route('/admin/supervision/coordinateur', name: 'app_admin_supervision_coordinateur', methods: ['GET'])]
    public function supervisionCoordinateur(
        UniteRepository $uniteRepository,
        AlerteRepository $alerteRepository,
        PatientRepository $patientRepository,
        PersonnelRepository $personnelRepository,
        UniteInitializer $uniteInitializer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $uniteInitializer->ensureUnitsExist();

        $unites = $uniteRepository->findAll();
        foreach ($unites as $u) {
            $uniteInitializer->updateUnitCharge($u);
        }

        $alertesActives = $alerteRepository->findBy(['statut' => 'en_cours'], ['heure' => 'DESC']);
        $toutesAlertes = $alerteRepository->findBy([], ['heure' => 'DESC']);
        $patientsCount = count($patientRepository->findBy(['statut' => 'en cours']));
        $personnelDisponible = $personnelRepository->findBy(['disponibilite' => true]);

        return $this->render('admin/supervision/coordinateur.html.twig', [
            'unites' => $unites,
            'alertes_actives' => $alertesActives,
            'toutes_alertes' => $toutesAlertes,
            'patients_count' => $patientsCount,
            'alertes_count' => count($alertesActives),
            'personnels_disponibles' => $personnelDisponible,
        ]);
    }

    /* =========================================================================
       GESTION DES COMPTES UTILISATEURS & SEUILS DE SATURATION
       ========================================================================= */

    #[Route('/admin/utilisateur/nouveau', name: 'app_admin_nouveau_utilisateur', methods: ['POST'])]
    public function nouveauUtilisateur(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UniteRepository $uniteRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_nouveau_utilisateur', $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $nom = trim((string) $request->request->get('nom'));
        $prenom = trim((string) $request->request->get('prenom'));
        $email = trim((string) $request->request->get('email'));
        $role = trim((string) $request->request->get('role'));
        $motDePasse = trim((string) $request->request->get('mot_de_passe'));
        $idUnite = $request->request->get('id_unite') ? (int) $request->request->get('id_unite') : null;

        if (empty($email) || empty($nom) || empty($prenom) || empty($role) || empty($motDePasse)) {
            $this->addFlash('error', 'Les champs Nom, Prénom, Email, Rôle et Mot de passe sont obligatoires.');
            return $this->redirectToRoute('app_admin');
        }

        // Vérification de l'unicité de l'email pour éviter les erreurs SQL brutes
        if ($utilisateurRepository->findOneBy(['email' => $email])) {
            $this->addFlash('error', sprintf('Un utilisateur avec l\'adresse email "%s" existe déjà.', $email));
            return $this->redirectToRoute('app_admin');
        }

        $rolesValides = [
            Utilisateur::ROLE_ACCUEIL,
            Utilisateur::ROLE_TRIAGE,
            Utilisateur::ROLE_SOIGNANT,
            Utilisateur::ROLE_COORDINATEUR,
            Utilisateur::ROLE_ADMIN,
        ];

        if (!in_array($role, $rolesValides, true)) {
            $this->addFlash('error', 'Le rôle sélectionné est invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $user = new Utilisateur();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setActif(true);

        if ($idUnite) {
            $unite = $uniteRepository->find($idUnite);
            if ($unite) {
                $user->setUniteAssignee($unite);
            }
        }

        // Hashage sécurisé du mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $motDePasse);
        $user->setPassword($hashedPassword);

        // Si le rôle est soignant, triage ou accueil, associer/créer une fiche Personnel
        if (in_array($role, [Utilisateur::ROLE_SOIGNANT, Utilisateur::ROLE_TRIAGE, Utilisateur::ROLE_ACCUEIL], true)) {
            $personnel = new Personnel();
            $personnel->setNom($nom . ' ' . $prenom);
            $personnel->setRole($role);
            $personnel->setDisponibilite(true);
            if ($user->getUniteAssignee()) {
                $personnel->setUniteAssignee($user->getUniteAssignee());
            }
            $em->persist($personnel);
            $user->setPersonnel($personnel);
        }

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', sprintf('Utilisateur "%s %s" (%s) créé avec succès.', $prenom, $nom, $role));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/utilisateur/{id}/modifier', name: 'app_admin_modifier_utilisateur', methods: ['POST'])]
    public function modifierUtilisateur(
        int $id,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UniteRepository $uniteRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_modifier_utilisateur_' . $id, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $user = $utilisateurRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_admin');
        }

        $nom = trim((string) $request->request->get('nom'));
        $prenom = trim((string) $request->request->get('prenom'));
        $email = trim((string) $request->request->get('email'));
        $role = trim((string) $request->request->get('role'));
        $nouveauMotDePasse = trim((string) $request->request->get('mot_de_passe'));
        $idUnite = $request->request->get('id_unite') ? (int) $request->request->get('id_unite') : null;

        if (empty($email) || empty($nom) || empty($prenom) || empty($role)) {
            $this->addFlash('error', 'Le nom, prénom, email et rôle sont obligatoires.');
            return $this->redirectToRoute('app_admin');
        }

        // Vérifier l'unicité de l'email en ignorant l'utilisateur courant
        $existing = $utilisateurRepository->findOneBy(['email' => $email]);
        if ($existing && $existing->getIdUtilisateur() !== $user->getIdUtilisateur()) {
            $this->addFlash('error', sprintf('Un autre utilisateur utilise déjà l\'adresse email "%s".', $email));
            return $this->redirectToRoute('app_admin');
        }

        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setRole($role);

        if ($idUnite) {
            $unite = $uniteRepository->find($idUnite);
            $user->setUniteAssignee($unite);
        } else {
            $user->setUniteAssignee(null);
        }

        // Réinitialisation facultative du mot de passe
        if (!empty($nouveauMotDePasse)) {
            $user->setPassword($passwordHasher->hashPassword($user, $nouveauMotDePasse));
        }

        // Mettre à jour la fiche Personnel liée le cas échéant
        if ($user->getPersonnel()) {
            $user->getPersonnel()->setNom($nom . ' ' . $prenom);
            $user->getPersonnel()->setRole($role);
            if ($user->getUniteAssignee()) {
                $user->getPersonnel()->setUniteAssignee($user->getUniteAssignee());
            }
        }

        $em->flush();

        $this->addFlash('success', sprintf('Le compte %s a été mis à jour avec succès.', $user->getEmail()));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/utilisateur/{id}/toggle-actif', name: 'app_admin_toggle_utilisateur', methods: ['POST'])]
    public function toggleActif(
        int $id,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_toggle_' . $id, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $user = $utilisateurRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_admin');
        }

        $user->setActif(!$user->isActif());
        $em->flush();

        $this->addFlash('success', sprintf('Le compte %s est désormais %s.', $user->getEmail(), $user->isActif() ? 'actif' : 'inactif'));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/utilisateur/{id}/supprimer', name: 'app_admin_supprimer_utilisateur', methods: ['POST'])]
    public function supprimerUtilisateur(
        int $id,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_supprimer_' . $id, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $user = $utilisateurRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_admin');
        }

        $email = $user->getEmail();

        // Dissociation propre de l'entité Personnel pour préserver l'historique médical sans suppression en cascade
        $user->setPersonnel(null);
        $user->setUniteAssignee(null);

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', sprintf('Le compte utilisateur %s a été supprimé définitivement. La fiche personnel a été conservée pour l\'historique.', $email));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/unite/{id}/seuil', name: 'app_admin_seuil_unite', methods: ['POST'])]
    public function majSeuilUnite(
        int $id,
        Request $request,
        UniteRepository $uniteRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_seuil_' . $id, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin');
        }

        $unite = $uniteRepository->find($id);
        if (!$unite) {
            $this->addFlash('error', 'Unité introuvable.');
            return $this->redirectToRoute('app_admin');
        }

        $capacite = (int) $request->request->get('capacite_max');
        if ($capacite > 0) {
            $unite->setCapaciteMax($capacite);
            $em->flush();
            $this->addFlash('success', sprintf('Capacité maximale de l\'unité %s mise à jour à %d lits.', $unite->getNom(), $capacite));
        } else {
            $this->addFlash('error', 'La capacité maximale doit être un nombre strictly positif.');
        }

        return $this->redirectToRoute('app_admin');
    }
}
