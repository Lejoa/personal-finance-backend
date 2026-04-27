<?php

namespace App\Repository;

use App\Entity\BudgetCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetCategory>
 */
class BudgetCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetCategory::class);
    }

    /**
     * Finds a BudgetCategory by its primary key, scoped to the given Budget.
     * Returns null if the record does not exist or belongs to a different budget.
     * Prevents cross-budget data access at the database query level.
     */
    public function findByIdForBudget(int $id, int $budgetId): ?BudgetCategory
    {
        return $this->createQueryBuilder('bc')
            ->where('bc.id = :id')
            ->andWhere('bc.budget = :budgetId')
            ->setParameter('id', $id)
            ->setParameter('budgetId', $budgetId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
