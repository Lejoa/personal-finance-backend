<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * Finds a Budget by its primary key, scoped to the given user.
     * Returns null if the record does not exist or belongs to a different user.
     * Prevents cross-user data access (IDOR) at the database query level.
     */
    public function findByIdForUser(int $id, User $user): ?Budget
    {
        return $this->createQueryBuilder('b')
            ->where('b.id = :id')
            ->andWhere('b.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
