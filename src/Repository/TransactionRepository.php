<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Find transactions with filters: type, date range and limit
     * @return Transaction[]
     */
    public function findByFilters(
        User $user,
        ?string $type = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?int $limit = null,
        ?string $synchronized = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($startDate !== null) {
            $qb->andWhere('t.date >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('t.date <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($synchronized !== null) {
            $qb->andWhere('t.synchronized = :synchronized')
               ->setParameter('synchronized', $synchronized);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns aggregated financial data for the last 30 days:
     * total income, total expenses, and top 3 expense category names.
     *
     * @return array{total_income: float, total_expenses: float, top_expense_categories: string[]}
     */
    public function getUserFinancialSummary(User $user): array
    {
        $since = new \DateTime('-30 days');

        $totals = $this->createQueryBuilder('t')
            ->select('t.type, SUM(t.amount) as total')
            ->where('t.user = :user')
            ->andWhere('t.date >= :since')
            ->groupBy('t.type')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $totalIncome = 0.0;
        $totalExpenses = 0.0;
        foreach ($totals as $row) {
            if ($row['type'] === 'ingreso') {
                $totalIncome = (float) $row['total'];
            } elseif ($row['type'] === 'gasto') {
                $totalExpenses = (float) $row['total'];
            }
        }

        $topCategories = $this->createQueryBuilder('t')
            ->select('c.name, SUM(t.amount) as total')
            ->join('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :since')
            ->groupBy('c.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults(3)
            ->setParameter('user', $user)
            ->setParameter('type', 'gasto')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $topExpenseCategories = array_map(
            fn($row) => mb_strtolower($row['name']),
            $topCategories
        );

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'top_expense_categories' => $topExpenseCategories,
        ];
    }
}
