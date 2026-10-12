<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Rôles possibles — utilisés par Symfony Security (access_control dans security.yaml)
    public const ROLE_ACCUEIL = 'ROLE_ACCUEIL';
    public const ROLE_TRIAGE = 'ROLE_TRIAGE';
    public const ROLE_SOIGNANT = 'ROLE_SOIGNANT';
    public const ROLE_COORDINATEUR = 'ROLE_COORDINATEUR';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: 'integer')]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'nom', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: 'prenom', length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(name: 'email', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe')]
    private ?string $motDePasse = null;

    // Rôle métier stocké en base (ex: 'ROLE_TRIAGE') — un seul rôle principal par utilisateur ici
    #[ORM\Column(name: 'role', length: 50)]
    private ?string $role = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(name: 'id_unite_assignee', referencedColumnName: 'id_unite', nullable: true, onDelete: 'SET NULL')]
    private ?Unite $uniteAssignee = null;

    // Lien vers la fiche métier Personnel (peut être NULL pour un admin/coordinateur)
    #[ORM\ManyToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(name: 'id_personnel', referencedColumnName: 'id_personnel', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $personnel = null;

    #[ORM\Column(name: 'disponibilite', nullable: true, options: ['default' => 1])]
    private ?bool $disponibilite = true;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'actif', nullable: true, options: ['default' => 1])]
    private ?bool $actif = true;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->idUtilisateur;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identifiant "technique" utilisé par Symfony Security pour retrouver l'utilisateur (ici : l'email).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function setPassword(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    /**
     * Symfony Security attend un tableau de rôles (ex: ['ROLE_TRIAGE', 'ROLE_USER']).
     * On construit ce tableau à partir du champ `role` stocké en base.
     */
    public function getRoles(): array
    {
        $roles = [$this->role];
        $roles[] = 'ROLE_USER'; // rôle de base, toujours présent
        return array_unique($roles);
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getUniteAssignee(): ?Unite
    {
        return $this->uniteAssignee;
    }

    public function setUniteAssignee(?Unite $uniteAssignee): static
    {
        $this->uniteAssignee = $uniteAssignee;

        return $this;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;

        return $this;
    }

    public function isDisponibilite(): ?bool
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?bool $disponibilite): static
    {
        $this->disponibilite = $disponibilite;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(?bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    /**
     * Requis par UserInterface — sert à effacer des données sensibles temporaires (ex: mot de passe en clair
     * saisi lors d'un formulaire). Rien à faire ici car on ne stocke jamais le mot de passe en clair.
     */
    public function eraseCredentials(): void
    {
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function setDateCreation(?\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }
}
