<?php

namespace App\Repository;

use App\Entity\FinancialAnalysis;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinancialAnalysis>
 */
class FinancialAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialAnalysis::class);
    }

    /**
     * @return FinancialAnalysis[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserPeriodCheckpoint(User $user, string $period, string $checkpoint): ?FinancialAnalysis
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.period = :period')
            ->andWhere('a.checkpoint = :checkpoint')
            ->setParameter('user', $user)
            ->setParameter('period', $period)
            ->setParameter('checkpoint', $checkpoint)
            ->getQuery()
            ->getOneOrNullResult();
    }
}