<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un utilisateur (compte de connexion) avec mot de passe hashé.',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe en clair (sera hashé)')
            ->addArgument('nom', InputArgument::REQUIRED, 'Nom')
            ->addArgument('prenom', InputArgument::REQUIRED, 'Prénom')
            ->addArgument('role', InputArgument::REQUIRED, 'Rôle : ROLE_ACCUEIL, ROLE_TRIAGE, ROLE_SOIGNANT, ROLE_COORDINATEUR ou ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rolesValides = [
            Utilisateur::ROLE_ACCUEIL,
            Utilisateur::ROLE_TRIAGE,
            Utilisateur::ROLE_SOIGNANT,
            Utilisateur::ROLE_COORDINATEUR,
            Utilisateur::ROLE_ADMIN,
        ];

        $role = $input->getArgument('role');
        if (!in_array($role, $rolesValides, true)) {
            $io->error('Rôle invalide. Utilise un de ceux-ci : ' . implode(', ', $rolesValides));
            return Command::FAILURE;
        }

        $user = new Utilisateur();
        $user->setEmail($input->getArgument('email'));
        $user->setNom($input->getArgument('nom'));
        $user->setPrenom($input->getArgument('prenom'));
        $user->setRole($role);

        // Hash du mot de passe : jamais stocké en clair
        $motDePasseHashe = $this->passwordHasher->hashPassword($user, $input->getArgument('password'));
        $user->setPassword($motDePasseHashe);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Utilisateur "%s" créé avec le rôle %s.', $user->getEmail(), $role));

        return Command::SUCCESS;
    }
}