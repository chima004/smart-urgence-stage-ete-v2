<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    /**
     * @return Patient[] Returns patients awaiting triage (gravite is NULL and statut is 'en cours')
     */
    public function findPatientsEnAttenteTriage(): array
    {
        return $this->findBy(
            ['gravite' => null, 'statut' => 'en cours'],
            ['dateArrivee' => 'ASC']
        );
    }

    /**
     * @return Patient[] Returns recent triaged patients (gravite is NOT NULL)
     */
    public function findPatientsTriesRecents(int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.gravite IS NOT NULL')
            ->orderBy('p.dateArrivee', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
