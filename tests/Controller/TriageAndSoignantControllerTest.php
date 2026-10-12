<?php

namespace App\Tests\Controller;

use App\Entity\Alerte;
use App\Entity\Utilisateur;
use App\Entity\Unite;
use App\Entity\Personnel;
use App\Entity\Reaffectation;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UniteInitializer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TriageAndSoignantControllerTest extends WebTestCase
{
    private function getOrCreateTestUser(string $email, string $role, string $uniteNom): Utilisateur
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $uniteInitializer = new UniteInitializer($em, $container->get(UniteRepository::class));
        $uniteInitializer->ensureUnitsExist();

        $userRepository = $container->get(UtilisateurRepository::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $uniteRepo = $container->get(UniteRepository::class);
            $unite = $uniteRepo->findOneBy(['nom' => $uniteNom]) ?? $uniteRepo->findAll()[0];

            $user = new Utilisateur();
            $user->setEmail($email);
            $user->setNom('TestUser_' . $role);
            $user->setPrenom('TestPrenom');
            $user->setRole($role);
            $user->setMotDePasse('password');

            $personnel = new Personnel();
            $personnel->setNom('TestUser_' . $role);
            $personnel->setRole($role);
            $personnel->setUniteAssignee($unite);
            $em->persist($personnel);

            $user->setPersonnel($personnel);
            $user->setUniteAssignee($unite);

            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    public function testTriageIndexPageRendersWithoutErrors(): void
    {
        $client = static::createClient();
        $testUser = $this->getOrCreateTestUser('triage_test@urgences.tn', 'ROLE_TRIAGE', 'Triage');

        $client->loginUser($testUser);
        $client->request('GET', '/triage');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Poste de Triage');
    }

    public function testSoignantIndexPageRendersWithoutErrors(): void
    {
        $client = static::createClient();
        $testUser = $this->getOrCreateTestUser('soignant_test@urgences.tn', 'ROLE_SOIGNANT', 'Box');

        $client->loginUser($testUser);
        $client->request('GET', '/soignant');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Poste Soignant');
    }

    public function testSoignantInTriageRenfortModeRendersWithoutErrors(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $uniteRepo = $container->get(UniteRepository::class);

        $testUser = $this->getOrCreateTestUser('soignant_renfort@urgences.tn', 'ROLE_SOIGNANT', 'Box');
        $triageUnite = $uniteRepo->findOneBy(['nom' => 'Triage']);

        $alerte = new Alerte();
        $alerte->setUnite($triageUnite);
        $alerte->setType('saturation');
        $alerte->setStatut('en_cours');
        $alerte->setHeure(new \DateTime());
        $em->persist($alerte);

        // Create an active reaffectation to Triage
        $reaffectation = new Reaffectation();
        $reaffectation->setAlerte($alerte);
        $reaffectation->setPersonnel($testUser->getPersonnel());
        $reaffectation->setUniteCible($triageUnite);
        $reaffectation->setReponse('acceptee');
        $reaffectation->setRenfortTermine(false);
        $reaffectation->setHeureNotification(new \DateTime());
        $em->persist($reaffectation);
        $em->flush();

        $client->loginUser($testUser);
        $client->request('GET', '/soignant');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mode Renfort Triage');
    }
}
