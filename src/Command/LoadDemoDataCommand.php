<?php

namespace App\Command;

use App\Entity\Alerte;
use App\Entity\Passage;
use App\Entity\Patient;
use App\Entity\Personnel;
use App\Entity\Reaffectation;
use App\Entity\Unite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-demo-data',
    description: 'Injecte un jeu de données de test réaliste pour la démo/soutenance.',
)]
class LoadDemoDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $unites = $this->em->getRepository(Unite::class)->findAll();
        if (count($unites) < 5) {
            $io->error('Il faut d\'abord avoir les 5 unités en base (Accueil, Triage, Box, SAUV, UHCD).');
            return Command::FAILURE;
        }
        $uniteParNom = [];
        foreach ($unites as $u) {
            $uniteParNom[$u->getNom()] = $u;
        }

        $io->section('Création du personnel');
        $personnelsData = [
            ['Ben Salah', 'infirmier', 'Box'],
            ['Trabelsi', 'medecin', 'Box'],
            ['Gharbi', 'infirmier', 'SAUV'],
            ['Chaabane', 'infirmier', 'UHCD'],
            ['Jebali', 'infirmier', 'Accueil'],
        ];
        $personnels = [];
        foreach ($personnelsData as [$nom, $role, $uniteNom]) {
            $p = new Personnel();
            $p->setNom($nom);
            $p->setRole($role);
            $p->setDisponibilite(true);
            $p->setUniteAssignee($uniteParNom[$uniteNom] ?? null);
            $this->em->persist($p);
            $personnels[] = $p;
        }
        $this->em->flush();
        $io->text(count($personnels) . ' fiches personnel créées.');

        $io->section('Création des patients et de leurs passages');
        $gravites = ['rouge', 'orange', 'jaune', 'vert'];
        $prenoms = ['Fatma', 'Ahmed', 'Salma', 'Karim', 'Amel', 'Mohamed', 'Nadia', 'Sami',
                    'Ines', 'Youssef', 'Rania', 'Bilel', 'Emna', 'Hedi', 'Mariem', 'Walid',
                    'Sonia', 'Anis', 'Leila', 'Marwen', 'Hana', 'Foued', 'Asma', 'Nizar', 'Nour'];

        $nbCrees = 0;
        foreach ($prenoms as $i => $prenom) {
            $patient = new Patient();
            $patient->setNom($prenom);
            $patient->setAge(random_int(2, 85));
            $patient->setSexe($i % 2 === 0 ? 'Femme' : 'Homme');
            $gravite = $gravites[array_rand($gravites)];
            $patient->setGravite($gravite);

            $heureArrivee = (new \DateTime())->modify('-' . random_int(5, 360) . ' minutes');
            $patient->setDateArrivee($heureArrivee);

            $enCours = $i % 4 !== 0;
            $patient->setStatut($enCours ? 'en cours' : 'sorti');

            $this->em->persist($patient);

            $uniteDestNom = match ($gravite) {
                'rouge' => 'SAUV',
                'orange' => 'Box',
                'jaune' => 'Box',
                'vert' => 'UHCD',
            };
            $uniteDest = $uniteParNom[$uniteDestNom];

            $passageAccueil = new Passage();
            $passageAccueil->setPatient($patient);
            $passageAccueil->setUnite($uniteParNom['Accueil']);
            $passageAccueil->setHeureEntree($heureArrivee);
            $passageAccueil->setHeureSortie((clone $heureArrivee)->modify('+5 minutes'));
            $this->em->persist($passageAccueil);

            $passageUnite = new Passage();
            $passageUnite->setPatient($patient);
            $passageUnite->setUnite($uniteDest);
            $passageUnite->setHeureEntree((clone $heureArrivee)->modify('+10 minutes'));
            if (!$enCours) {
                $passageUnite->setHeureSortie((clone $heureArrivee)->modify('+' . random_int(30, 90) . ' minutes'));
            }
            $this->em->persist($passageUnite);

            $nbCrees++;
        }
        $this->em->flush();
        $io->text($nbCrees . ' patients créés avec leur historique de passages.');

        $io->section('Recalcul de la charge de chaque unité');
        foreach ($unites as $unite) {
            $nbActifs = $this->em->getRepository(Passage::class)->createQueryBuilder('p')
                ->select('COUNT(p.idPassage)')
                ->where('p.unite = :unite')
                ->andWhere('p.heureSortie IS NULL')
                ->setParameter('unite', $unite)
                ->getQuery()
                ->getSingleScalarResult();

            $unite->setChargeActuelle((int) $nbActifs);
            $io->text(sprintf('  - %s : %d / %d', $unite->getNom(), $nbActifs, $unite->getCapaciteMax()));

            if ($unite->estSature()) {
                $alerte = new Alerte();
                $alerte->setUnite($unite);
                $alerte->setType('saturation');
                $alerte->setHeure(new \DateTime());
                $alerte->setStatut('en_cours');
                $this->em->persist($alerte);

                $personnelDispo = $this->em->getRepository(Personnel::class)->findOneBy(['disponibilite' => true]);
                if ($personnelDispo) {
                    $reaffectation = new Reaffectation();
                    $reaffectation->setAlerte($alerte);
                    $reaffectation->setPersonnel($personnelDispo);
                    $reaffectation->setHeureNotification(new \DateTime());
                    $reaffectation->setReponse('en_attente');
                    $this->em->persist($reaffectation);
                }
            }
        }
        $this->em->flush();

        $io->success('Jeu de données de démo injecté avec succès.');

        return Command::SUCCESS;
    }
}