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

    /**
     * Returns the budgeted amounts for a category across previous budgets of the same user,
     * excluding the budget that contains the given BudgetCategory.
     * Used to compute historical average limits for formative feedback.
     *
     * @return float[]
     */
    public function getPreviousBudgetAmounts(BudgetCategory $budgetCategory, int $limit = 3): array
    {
        $category = $budgetCategory->getCategory();
        $currentBudget = $budgetCategory->getBudget();
        $user = $currentBudget->getUser();

        $rows = $this->createQueryBuilder('bc')
            ->select('bc.amount')
            ->join('bc.budget', 'b')
            ->where('bc.category = :category')
            ->andWhere('b.user = :user')
            ->andWhere('b.id != :currentBudgetId')
            ->orderBy('b.startDate', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('category', $category)
            ->setParameter('user', $user)
            ->setParameter('currentBudgetId', $currentBudget->getId())
            ->getQuery()
            ->getResult();

        return array_column($rows, 'amount');
    }
}
